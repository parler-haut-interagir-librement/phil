<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Application;

use FilesystemIterator;
use Phil\Fonts\Font;
use Phil\Fonts\FontStyle;
use Phil\Fonts\Format;
use Phil\Fonts\Licence\LicenceRecord;
use Phil\Fonts\Licence\LicenceRegister;
use Phil\FontsBundle\Application\Exception\FontNotFound;
use Phil\FontsBundle\Application\Exception\FontUnreadable;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The font files of a directory and its folders, read once: `.ttf`, `.otf`, `.ttc`, `.otc`, `.woff` and `.woff2`,
 * extension case aside. A file that cannot be read — a WOFF2 file without `ext-brotli`, a damaged file — stays in the
 * catalogue with the reason, so that the commands can show it; the CSS and the lookups use the files that were read.
 */
final class FontCatalogue
{
    /** The format each extension announces, until the file is read. */
    public const array EXTENSIONS = [
        'ttf' => Format::TrueType,
        'otf' => Format::OpenType,
        'ttc' => Format::Collection,
        'otc' => Format::Collection,
        'woff' => Format::Woff,
        'woff2' => Format::Woff2,
    ];

    /** @var list<CatalogueEntry>|null */
    private ?array $entries = null;

    public function __construct(
        private readonly string $directory,
        private readonly FontFiles $files = new FontFiles(),
    ) {
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function files(): FontFiles
    {
        return $this->files;
    }

    /**
     * Every font file, sorted by path; none when the directory does not exist.
     *
     * @return list<CatalogueEntry>
     */
    public function entries(): array
    {
        return $this->entries ??= $this->scan();
    }

    /**
     * The files that could not be read.
     *
     * @return list<CatalogueEntry>
     */
    public function unreadable(): array
    {
        return array_values(array_filter($this->entries(), static fn (CatalogueEntry $entry): bool => !$entry->isReadable()));
    }

    /**
     * A file by its path relative to the directory.
     */
    public function get(string $path): CatalogueEntry
    {
        return array_find($this->entries(), static fn (CatalogueEntry $entry): bool => $entry->path === $path)
            ?? throw FontNotFound::path($path, $this->directory);
    }

    /**
     * The first file, by path, with a face of the family — case aside —, the weight — within the range of a variable
     * font — and the style given; among the formats given when there are any, in their order.
     */
    public function find(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal, Format ...$formats): ?CatalogueEntry
    {
        return $this->locate($family, $weight, $style, $formats)[0] ?? null;
    }

    /**
     * The face of the family, weight and style in the formats given, or `FontNotFound`.
     */
    public function require(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal, Format ...$formats): CatalogueEntry
    {
        return ($this->locate($family, $weight, $style, $formats) ?? throw $this->notFound($family, $weight, $style, $formats))[0];
    }

    /**
     * The face itself of the family, weight and style in the formats given, from the file `require()` finds.
     */
    public function face(string $family, int $weight = 400, FontStyle $style = FontStyle::Normal, Format ...$formats): Font
    {
        return ($this->locate($family, $weight, $style, $formats) ?? throw $this->notFound($family, $weight, $style, $formats))[1];
    }

    /**
     * The licence records of the files that were read; a file that could not be read keeps the record of the register
     * given, when it has one, so that a WOFF2 file read elsewhere does not look removed where `ext-brotli` is missing.
     */
    public function licences(LicenceRegister $kept = new LicenceRegister()): LicenceRegister
    {
        $records = [];

        foreach ($this->entries() as $entry) {
            $record = null === $entry->file ? $kept->get($entry->path) : LicenceRecord::of($entry->path, $entry->file);

            if ($record instanceof LicenceRecord) {
                $records[] = $record;
            }
        }

        return new LicenceRegister($records);
    }

    /**
     * The first file and face that match, among the formats in their order when there are any.
     *
     * @param array<Format> $formats
     *
     * @return array{CatalogueEntry, Font}|null
     */
    private function locate(string $family, int $weight, FontStyle $style, array $formats): ?array
    {
        foreach ([] === $formats ? [null] : $formats as $format) {
            foreach ($this->entries() as $entry) {
                $font = null === $format || $entry->format === $format
                    ? array_find($entry->file->faces ?? [], static fn (Font $font): bool => self::matches($font, $family, $weight, $style))
                    : null;

                if ($font instanceof Font) {
                    return [$entry, $font];
                }
            }
        }

        return null;
    }

    /**
     * @param array<Format> $formats
     */
    private function notFound(string $family, int $weight, FontStyle $style, array $formats): FontNotFound
    {
        return FontNotFound::face($family, $weight, $style->value, [] === $formats ? 'font' : implode(' or ', array_map(static fn (Format $format): string => $format->value, $formats)));
    }

    private static function matches(Font $font, string $family, int $weight, FontStyle $style): bool
    {
        [$lightest, $boldest] = $font->weightRange();

        return 0 === strcasecmp($font->family(), $family) && $font->style === $style && $weight >= $lightest && $weight <= $boldest;
    }

    /**
     * @return list<CatalogueEntry>
     */
    private function scan(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $entries = [];
        $root = \strlen(rtrim($this->directory, '/\\')) + 1;

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $format = self::EXTENSIONS[strtolower($file->getExtension())] ?? null;

            if (null === $format) {
                continue;
            }

            $path = str_replace(\DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), $root));

            try {
                $read = $this->files->read($file->getPathname());
                $entries[$path] = new CatalogueEntry($path, $file->getPathname(), $read->format, $read);
            } catch (FontUnreadable $fontUnreadable) {
                $entries[$path] = new CatalogueEntry($path, $file->getPathname(), $format, error: $fontUnreadable->getMessage());
            }
        }

        ksort($entries, \SORT_STRING);

        return array_values($entries);
    }
}
