<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Archive;

use Override;
use Phil\ElearningPackages\Contract\PackageArchive;
use Phil\ElearningPackages\Exception\PackageRefused;
use Phil\ElearningPackages\PackageLimits;
use Phil\ElearningPackages\PackagePath;
use ZipArchive;

/**
 * A ZIP package read through PHP's zip extension, checked as a whole before any file is read: its size and number of
 * entries; each entry's name, which must stay inside the package and be unique whatever its case; no symbolic link and
 * no encrypted entry; the size each entry announces, alone and summed, and its compression ratio. A file is then read
 * through a stream that stops one byte past what the entry announced, so that an archive whose headers lie is refused
 * rather than believed, as is an entry whose data does not match its CRC-32; the bytes read across the package are
 * counted against the same total.
 */
final class ZipPackage implements PackageArchive
{
    private const int SYMLINK = 0xA000;

    private const int TYPE_MASK = 0xF000;

    private const int RATIO_FLOOR = 1024 * 1024;

    private const int CHUNK = 65_536;

    /** @var list<string> the files, in the order of the archive */
    private array $names = [];

    /** @var array<string, int> the announced size of each file, by name */
    private array $sizes = [];

    /** @var array<string, int> the announced CRC-32 of each file, by name */
    private array $checksums = [];

    private int $read = 0;

    private bool $open = true;

    private function __construct(
        private readonly ZipArchive $zip,
        private readonly PackageLimits $limits,
    ) {
    }

    /**
     * @throws PackageRefused when the file is not a ZIP archive, or one past the limits or holding an unsafe entry
     */
    public static function open(string $path, PackageLimits $limits): self
    {
        $size = is_file($path) ? filesize($path) : false;

        if (false === $size || $size > $limits->archiveBytes) {
            throw PackageRefused::because(\sprintf('The package is not a readable file of %d bytes at most.', $limits->archiveBytes));
        }

        $zip = new ZipArchive();

        if (true !== $zip->open($path, ZipArchive::RDONLY)) {
            throw PackageRefused::because('The package is not a ZIP archive.');
        }

        // An archive refused is released with the package, as soon as the exception leaves this method.
        $package = new self($zip, $limits);
        $package->inspect();

        return $package;
    }

    #[Override]
    public function files(): array
    {
        return $this->names;
    }

    #[Override]
    public function has(string $name): bool
    {
        return isset($this->sizes[$name]);
    }

    #[Override]
    public function read(string $name): string
    {
        $announced = $this->sizes[$name] ?? throw PackageRefused::because(\sprintf('The package has no file "%s".', mb_substr($name, 0, 120)));
        $stream = $this->open ? $this->zip->getStream($name) : false;

        if (false === $stream) {
            throw PackageRefused::because(\sprintf('The file "%s" of the package cannot be read.', $name));
        }

        // A corrupt entry — a CRC that does not match, data that ends early — is reported by the zip stream as a
        // warning: it refuses the file instead.
        set_error_handler(static fn (int $level, string $message): never => throw PackageRefused::because(\sprintf('The file "%s" of the package is corrupt: %s', $name, $message)));

        try {
            $contents = '';

            while (!feof($stream) && \strlen($contents) <= $announced) {
                $contents .= (string) fread($stream, self::CHUNK);
            }
        } finally {
            restore_error_handler();
            fclose($stream);
        }

        if (\strlen($contents) !== $announced) {
            throw PackageRefused::because(\sprintf('The file "%s" of the package is not the size its entry announces.', $name));
        }

        if (crc32($contents) !== ($this->checksums[$name] ?? null)) {
            throw PackageRefused::because(\sprintf('The file "%s" of the package is corrupt: its checksum does not match.', $name));
        }

        $this->read += $announced;

        if ($this->read > $this->limits->totalBytes) {
            throw PackageRefused::because(\sprintf('The package uncompresses to more than %d bytes.', $this->limits->totalBytes));
        }

        return $contents;
    }

    #[Override]
    public function close(): void
    {
        if ($this->open) {
            $this->open = false;
            $this->zip->close();
        }
    }

    private function inspect(): void
    {
        $count = $this->zip->count();

        if ($count > $this->limits->entries) {
            throw PackageRefused::because(\sprintf('The package holds more than %d entries.', $this->limits->entries));
        }

        $total = 0;
        $folded = [];

        for ($index = 0; $index < $count; ++$index) {
            $entry = $this->zip->statIndex($index, ZipArchive::FL_UNCHANGED);

            if (false === $entry) {
                throw PackageRefused::because('An entry of the package cannot be read.');
            }

            $name = $entry['name'];

            if (str_ends_with($name, '/') && 0 === $entry['size']) {
                PackagePath::check(rtrim($name, '/'));

                continue;
            }

            $this->entry($index, $name, $entry['size'], $entry['comp_size'], $entry['encryption_method']);
            $key = mb_strtolower($name, 'UTF-8');

            if (isset($folded[$key])) {
                throw PackageRefused::because(\sprintf('The package holds "%s" and "%s", which differ only by the case of their letters.', $folded[$key], $name));
            }

            $folded[$key] = $name;
            $total += $entry['size'];

            if ($total > $this->limits->totalBytes) {
                throw PackageRefused::because(\sprintf('The package uncompresses to more than %d bytes.', $this->limits->totalBytes));
            }

            $this->names[] = $name;
            $this->sizes[$name] = $entry['size'];
            $this->checksums[$name] = $entry['crc'];
        }
    }

    private function entry(int $index, string $name, int $size, int $compressed, int $encryption): void
    {
        PackagePath::check($name);

        if (0 !== $encryption) {
            throw PackageRefused::because(\sprintf('The entry "%s" of the package is encrypted.', $name));
        }

        $system = 0;
        $attributes = 0;

        if ($this->zip->getExternalAttributesIndex($index, $system, $attributes, ZipArchive::FL_UNCHANGED) && ZipArchive::OPSYS_UNIX === $system && \is_int($attributes) && self::SYMLINK === (($attributes >> 16) & self::TYPE_MASK)) {
            throw PackageRefused::because(\sprintf('The entry "%s" of the package is a symbolic link.', $name));
        }

        if ($size > $this->limits->entryBytes) {
            throw PackageRefused::because(\sprintf('The entry "%s" of the package uncompresses to more than %d bytes.', $name, $this->limits->entryBytes));
        }

        if ($size > self::RATIO_FLOOR && $size > $this->limits->ratio * $compressed) {
            throw PackageRefused::because(\sprintf('The entry "%s" of the package is compressed more than %d times: a compression bomb is refused.', $name, $this->limits->ratio));
        }
    }
}
