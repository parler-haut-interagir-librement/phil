<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\ReadLimits;
use ZipArchive;

/**
 * A ZIP archive opened for reading within limits: the file's size and its number of entries are checked when it is
 * opened, every entry name must stay inside the archive's root, and each part is read through a byte budget, so that
 * an archive that lies about its sizes is stopped as it is read. Nothing is ever extracted to the disk.
 *
 * @internal
 */
final readonly class Archive
{
    private function __construct(
        private ZipArchive $zip,
        private ReadLimits $limits,
    ) {
    }

    /**
     * @throws ReadRefused when the file is missing, too large, not an archive, has too many entries or an entry outside
     *                     the root
     */
    public static function open(string $path, ReadLimits $limits): self
    {
        $size = is_file($path) ? filesize($path) : false;

        if (false === $size) {
            throw ReadRefused::because('The file cannot be read.');
        }

        if ($size > $limits->maxFileBytes) {
            throw ReadRefused::overLimit('The size of the file', $limits->maxFileBytes);
        }

        $zip = new ZipArchive();

        if (true !== $zip->open($path, ZipArchive::RDONLY)) {
            throw ReadRefused::because('The file is not a ZIP archive.');
        }

        if ($zip->count() > $limits->maxEntries) {
            $zip->close();

            throw ReadRefused::overLimit('The number of entries of the archive', $limits->maxEntries);
        }

        for ($index = 0, $count = $zip->count(); $index < $count; ++$index) {
            $name = (string) $zip->getNameIndex($index);

            if (!self::isInsideRoot($name)) {
                $zip->close();

                throw ReadRefused::because(\sprintf('The entry "%s" of the archive points outside its root.', mb_scrub(mb_substr($name, 0, 100), 'UTF-8')));
            }
        }

        return new self($zip, $limits);
    }

    /**
     * Whether an entry name stays inside the archive's root: relative, with forward slashes, no `..` segment, no drive
     * and no NUL byte.
     */
    public static function isInsideRoot(string $name): bool
    {
        return '' !== $name && 1 !== preg_match('#^/|\\\\|\x00|^[A-Za-z]:|(?:^|/)\.\.(?:/|$)#', $name);
    }

    /**
     * The name of a part a relationship targets, resolved against the folder of the part that declares it: a target
     * starting with `/` starts from the root.
     *
     * @throws ReadRefused when the target goes above the root
     */
    public static function resolve(string $folder, string $target): string
    {
        $path = str_starts_with($target, '/') ? $target : $folder.'/'.$target;
        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ('..' === $segment) {
                if ([] === $segments) {
                    throw ReadRefused::because(\sprintf('The target "%s" points outside the archive.', mb_scrub(mb_substr($target, 0, 100), 'UTF-8')));
                }

                array_pop($segments);
            } elseif ('' !== $segment && '.' !== $segment) {
                $segments[] = $segment;
            }
        }

        return implode('/', $segments);
    }

    public function has(string $name): bool
    {
        return false !== $this->zip->locateName($name);
    }

    /**
     * A reader of an XML part, through the part's byte budget.
     *
     * @throws ReadRefused when the part is missing, encrypted, or declares more bytes than the limit
     */
    public function xml(string $name): SafeXmlReader
    {
        $budget = new ByteBudget($this->limits->maxPartBytes);

        return new SafeXmlReader($this->stream($name, $budget), $budget, $name, $this->limits->maxPartBytes);
    }

    /**
     * The bytes of a small part, read whole.
     *
     * @throws ReadRefused when the part is missing or larger than the bytes given
     */
    public function text(string $name, int $maxBytes): string
    {
        $budget = new ByteBudget($maxBytes);
        $stream = $this->stream($name, $budget);
        $text = @stream_get_contents($stream);
        fclose($stream);

        if (false === $text || $budget->exceeded) {
            throw ReadRefused::overLimit(\sprintf('The size of the part "%s"', $name), $maxBytes);
        }

        return $text;
    }

    public function close(): void
    {
        $this->zip->close();
    }

    /**
     * @return resource
     */
    private function stream(string $name, ByteBudget $budget): mixed
    {
        $stat = $this->zip->statName($name);

        if (false === $stat) {
            throw ReadRefused::because(\sprintf('The archive has no part "%s".', $name));
        }

        if (0 !== $stat['encryption_method']) {
            throw ReadRefused::because(\sprintf('The part "%s" is encrypted.', $name));
        }

        if ($stat['size'] > $budget->remaining) {
            throw ReadRefused::overLimit(\sprintf('The size of the part "%s"', $name), $budget->remaining);
        }

        $stream = $this->zip->getStream($name);

        if (false === $stream) {
            throw ReadRefused::because(\sprintf('The part "%s" cannot be read.', $name));
        }

        BudgetFilter::attach($stream, $budget);

        return $stream;
    }
}
