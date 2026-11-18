<?php

declare(strict_types=1);

namespace Phil\Backup\Storage;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Exception\InvalidBackupValue;

/**
 * Where backups are written and found: one directory, readable by its owner alone, holding sets named after the
 * database, the time in UTC and an optional label, such as `app-20261107-031500.sql` and
 * `app-20261107-031500.media.tar`. A stem already taken in the directory gets a copy number, `_2` and up, so that two
 * backups of the same label within the same second never overwrite each other.
 *
 * The directory is created with mode 0700. One that already exists and is open to its group or to other users is
 * narrowed to 0700 before anything is written in it, or refused when it cannot be narrowed or when
 * `tightenOpenDirectory` is false. A file of a set is written as a temporary file of the directory, readable by its
 * owner from the moment it exists, then given its name in one step, never over an existing file.
 */
final readonly class BackupDirectory
{
    public const string MEDIA = 'media.tar';

    /**
     * How many copies of one stem a directory may hold: the bound of the search for a free one.
     */
    public const int COPIES = 100;

    private const string NAME = '/^(?<stem>.+-(?<time>\d{8}-\d{6})(?:-(?<label>[a-z0-9][a-z0-9-]*))?(?:_(?<copy>[2-9]|[1-9]\d+))?)\.%s(?:\.%s)?$/D';

    public function __construct(
        private string $path,
        private bool $tightenOpenDirectory = true,
    ) {
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * The stem of a new set for the database taken at the time, the directory created if need be: with a copy number
     * when a set of the directory already has the stem.
     */
    public function newStem(string $database, DateTimeImmutable $at, string $label = ''): string
    {
        if ('' !== $label && 1 !== preg_match('/^[a-z0-9][a-z0-9-]*$/D', $label)) {
            throw InvalidBackupValue::because(\sprintf('The label "%s" holds characters other than lowercase letters, digits and "-", or starts with "-".', $label));
        }

        $this->create();
        $name = preg_replace('/[^A-Za-z0-9_-]+/', '-', pathinfo($database, \PATHINFO_FILENAME)) ?? 'database';

        $stem = \sprintf('%s-%s%s', $name, $at->setTimezone(new DateTimeZone('UTC'))->format('Ymd-His'), '' === $label ? '' : '-'.$label);

        for ($copy = 1; $copy <= self::COPIES; ++$copy) {
            $candidate = 1 === $copy ? $stem : $stem.'_'.$copy;

            if ([] === $this->glob($candidate.'.*')) {
                return $candidate;
            }
        }

        throw BackupFailed::because(\sprintf('The backup directory %s already holds %d backups named %s.', $this->path, self::COPIES, $stem));
    }

    /**
     * The path of a file of the directory.
     */
    public function file(string $name): string
    {
        return $this->path.'/'.$name;
    }

    /**
     * A new empty file of the directory, readable by its owner alone, for a copy in clear that lives while a backup
     * is encrypted, decrypted or tested; the caller removes it.
     */
    public function temporary(): string
    {
        $this->create();
        // tempnam() gives the directory without its symbolic links, and falls back to the system's when it cannot
        // write in it: a file elsewhere is removed and refused.
        $directory = (string) realpath($this->path);
        $file = @tempnam($directory, '.phil-backup-');

        if (false === $file || \dirname($file) !== $directory) {
            if (\is_string($file)) {
                unlink($file);
            }

            throw BackupFailed::because(\sprintf('No temporary file can be created in the backup directory %s.', $this->path));
        }

        return $file;
    }

    /**
     * Gives a temporary file of the directory the path of a file of the directory, in one step: the file appears
     * whole, with the temporary file's mode, and an existing file is never replaced.
     */
    public function publish(string $temporary, string $file): void
    {
        if (@link($temporary, $file)) {
            unlink($temporary);

            return;
        }

        if (file_exists($file)) {
            throw BackupFailed::because(\sprintf('The backup %s already exists: it is never replaced.', $file));
        }

        // A file system without hard links: the name was free a moment ago.
        if (!@rename($temporary, $file)) {
            throw BackupFailed::because(\sprintf('The backup %s cannot be written.', $file));
        }
    }

    /**
     * The sets of the directory whose database files have the extension, oldest first: the encrypted ones alone when
     * `encrypted` is true, those in clear alone when it is false, both when it is null.
     *
     * @return list<BackupSet>
     */
    public function sets(string $extension, ?bool $encrypted = null): array
    {
        $sets = [];
        $files = [
            ...(true === $encrypted ? [] : $this->glob('*.'.$extension)),
            ...(false === $encrypted ? [] : $this->glob('*.'.$extension.'.'.BackupCipher::EXTENSION)),
        ];

        foreach ($files as $file) {
            $set = $this->parse($file, $extension);

            if ($set instanceof BackupSet) {
                $sets[] = $set;
            }
        }

        usort($sets, BackupSet::compare(...));

        return $sets;
    }

    /**
     * The sets of the label given, oldest first, of the encryption given as `sets()` takes it.
     *
     * @return list<BackupSet>
     */
    public function labelled(string $extension, string $label, ?bool $encrypted = null): array
    {
        return array_values(array_filter($this->sets($extension, $encrypted), static fn (BackupSet $set): bool => $label === $set->label));
    }

    /**
     * The most recent set without a label, of the encryption given as `sets()` takes it, or null when there is none.
     */
    public function latest(string $extension, ?bool $encrypted = null): ?BackupSet
    {
        $sets = $this->labelled($extension, '', $encrypted);

        return [] === $sets ? null : end($sets);
    }

    /**
     * The set of a database file given by its path, anywhere: named as the directory names its files, or not, in
     * which case its time is its modification time and it has no media archive.
     */
    public function set(string $file, string $extension): BackupSet
    {
        if (!is_file($file)) {
            throw BackupFailed::because(\sprintf('The backup %s does not exist.', $file));
        }

        return $this->parse($file, $extension) ?? new BackupSet(basename($file), new DateTimeImmutable('@'.filemtime($file)), '', $file, null);
    }

    /**
     * Removes the files of the set.
     */
    public function remove(BackupSet $set): void
    {
        foreach ($set->files() as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function parse(string $file, string $extension): ?BackupSet
    {
        if (1 !== preg_match(\sprintf(self::NAME, preg_quote($extension, '/'), BackupCipher::EXTENSION), basename($file), $name)) {
            return null;
        }

        $time = DateTimeImmutable::createFromFormat('!Ymd-His', $name['time'], new DateTimeZone('UTC'));

        if (false === $time || $time->format('Ymd-His') !== $name['time']) {
            return null;
        }

        // The media archive has the encryption of the database file: an encrypted dump is never paired with an
        // archive in clear that anyone writing to the directory could have put beside it, nor the other way round.
        $media = \dirname($file).'/'.$name['stem'].'.'.self::MEDIA.(BackupSet::isEncrypted($file) ? '.'.BackupCipher::EXTENSION : '');
        $media = is_file($media) ? $media : null;

        return new BackupSet($name['stem'], $time, $name['label'] ?? '', $file, $media, (int) ($name['copy'] ?? 1));
    }

    /**
     * @return list<string>
     */
    private function glob(string $pattern): array
    {
        $files = glob($this->path.'/'.$pattern);

        return false === $files ? [] : $files;
    }

    private function create(): void
    {
        if (!is_dir($this->path) && !@mkdir($this->path, 0o700, true)) {
            throw BackupFailed::because(\sprintf('The backup directory %s cannot be created.', $this->path));
        }

        // Windows has no group or other users' bits to narrow.
        if ('Windows' === \PHP_OS_FAMILY) {
            return;
        }

        clearstatcache(true, $this->path);
        $mode = fileperms($this->path) & 0o777;

        if (0 === ($mode & 0o077)) {
            return;
        }

        if (!$this->tightenOpenDirectory) {
            throw BackupFailed::because(\sprintf('The backup directory %s is open to other users (mode %04o): narrow it to its owner, with mode 0700.', $this->path, $mode));
        }

        if (!@chmod($this->path, 0o700)) {
            throw BackupFailed::because(\sprintf('The backup directory %s is open to other users (mode %04o) and cannot be narrowed to its owner: give it to the user that writes the backups, with mode 0700.', $this->path, $mode));
        }
    }
}
