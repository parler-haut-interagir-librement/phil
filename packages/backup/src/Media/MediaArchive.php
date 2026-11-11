<?php

declare(strict_types=1);

namespace Phil\Backup\Media;

use Closure;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Exception\InvalidBackupValue;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * The media files of an application — uploads, generated images, documents — kept beside its database backup as
 * one tar archive. Each directory is named, and its files are archived under its name: `uploads/2026/11/poster.jpg`.
 * The names, not the paths, tie an archive to the directories, so a backup restores on a machine whose directories
 * are elsewhere.
 *
 * Only directories and regular files are archived: a symbolic link, which could point anywhere, is left out. A
 * directory that does not exist yet is archived empty. A restore leaves the files the archive does not hold, unless
 * it prunes them.
 */
final readonly class MediaArchive
{
    /**
     * @param array<string, string> $directories each name to its directory
     */
    public function __construct(
        private array $directories,
    ) {
        foreach (array_keys($directories) as $name) {
            if (1 !== preg_match('/^[A-Za-z0-9_-]+$/D', $name)) {
                throw InvalidBackupValue::because(\sprintf('The media name "%s" holds characters other than letters, digits, "-" and "_".', $name));
            }
        }
    }

    public function isEmpty(): bool
    {
        return [] === $this->directories;
    }

    /**
     * Writes every directory to the archive, sorted by path so that the same files give the same archive, and gives
     * the number of files back.
     */
    public function write(string $file): int
    {
        $writer = TarWriter::create($file);
        $files = 0;

        foreach ($this->directories as $name => $directory) {
            $writer->addDirectory($name, 0o755, is_dir($directory) ? (int) filemtime($directory) : 0);

            foreach ($this->entries($directory) as $relative => $entry) {
                if ($entry->isDir()) {
                    $writer->addDirectory($name.'/'.$relative, (int) $entry->getPerms(), (int) $entry->getMTime());
                } else {
                    $writer->addFile($name.'/'.$relative, $entry->getPathname(), (int) $entry->getPerms(), (int) $entry->getMTime());
                    ++$files;
                }
            }
        }

        $writer->close();

        return $files;
    }

    /**
     * Reads the archive whole, checking every header and every path, without writing anything, and gives the number
     * of files back.
     */
    public function verify(string $file): int
    {
        $files = 0;

        new TarReader($file)->read(function (TarEntry $entry) use (&$files): void {
            $this->target($entry->name);
            $files += $entry->directory ? 0 : 1;
        });

        return $files;
    }

    /**
     * Writes the files of the archive into their directories, over the files of the same path, once the whole archive
     * is verified; files the archive does not hold are left. Gives the number of files back.
     */
    public function extract(string $file): int
    {
        $this->verify($file);
        $files = 0;

        new TarReader($file)->read(function (TarEntry $entry, Closure $copy) use (&$files): void {
            [$base, $relative] = $this->target($entry->name);
            $target = '' === $relative ? $base : $base.'/'.$relative;
            $parent = $entry->directory ? $target : \dirname($target);

            if (!is_dir($parent) && !@mkdir($parent, 0o755, true)) {
                throw BackupFailed::because(\sprintf('The media directory %s cannot be created.', $parent));
            }

            $real = realpath($parent);
            $root = realpath($base);

            if (false === $real || false === $root || ($real !== $root && !str_starts_with($real, $root.\DIRECTORY_SEPARATOR))) {
                throw BackupFailed::because(\sprintf('The media path %s leads out of its directory through a link.', $entry->name));
            }

            if ($entry->directory) {
                return;
            }

            if (is_link($target)) {
                unlink($target);
            }

            $copy($target);
            chmod($target, $entry->mode & 0o777);
            touch($target, $entry->modified);
            ++$files;
        });

        return $files;
    }

    /**
     * Removes what the media directories hold and the archive does not, once the whole archive is read: files,
     * links and special files, then the directories left empty. Only the directories the archive names are pruned,
     * a link is removed without being followed, and a directory the archive holds is kept even when empty. Gives the
     * number of entries removed, directories aside, back.
     */
    public function prune(string $file): int
    {
        $held = [];

        new TarReader($file)->read(function (TarEntry $entry) use (&$held): void {
            [$base, $relative] = $this->target($entry->name);
            $held[$base][$relative] = true;
        });

        $removed = 0;

        foreach ($held as $base => $paths) {
            foreach (array_reverse($this->entries($base, true), true) as $relative => $entry) {
                if (isset($paths[$relative])) {
                    continue;
                }

                if (!$entry->isLink() && $entry->isDir()) {
                    if ([] === array_diff((array) scandir($entry->getPathname()), ['.', '..'])) {
                        rmdir($entry->getPathname());
                    }

                    continue;
                }

                unlink($entry->getPathname());
                ++$removed;
            }
        }

        return $removed;
    }

    /**
     * The directory and the path inside it that an archive path stands for; a path naming no configured directory,
     * or holding an empty, `.` or `..` segment, is refused.
     *
     * @return array{string, string}
     */
    private function target(string $path): array
    {
        $segments = explode('/', rtrim($path, '/'));
        $name = array_shift($segments);

        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                throw BackupFailed::because(\sprintf('The media path %s is not a plain relative path.', $path));
            }
        }

        if (!isset($this->directories[$name])) {
            throw BackupFailed::because(\sprintf('The archive holds media of "%s", which the configuration does not name.', $name));
        }

        return [$this->directories[$name], implode('/', $segments)];
    }

    /**
     * The directories and regular files under the directory, by relative path, sorted; with `everything`, links and
     * special files too, never followed.
     *
     * @return array<string, SplFileInfo>
     */
    private function entries(string $directory, bool $everything = false): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $directory = rtrim($directory, '/');
        $entries = [];

        try {
            /** @var SplFileInfo $entry */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
                if ($everything || (!$entry->isLink() && ($entry->isDir() || $entry->isFile()))) {
                    $entries[substr($entry->getPathname(), \strlen($directory) + 1)] = $entry;
                }
            }
        } catch (UnexpectedValueException $unexpectedValueException) {
            throw BackupFailed::because(\sprintf('The media directory %s cannot be read whole: %s', $directory, $unexpectedValueException->getMessage()));
        }

        ksort($entries, \SORT_STRING);

        return $entries;
    }
}
