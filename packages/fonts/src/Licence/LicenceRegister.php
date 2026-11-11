<?php

declare(strict_types=1);

namespace Phil\Fonts\Licence;

use Closure;
use FilesystemIterator;
use JsonException;
use Phil\Fonts\Exception\InvalidLicenceRegister;
use Phil\Fonts\FontFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The licence records of a set of font files, one per file, by path: built by scanning a directory, kept as JSON next
 * to the fonts, and compared with a new scan to find the files added, removed or changed since.
 */
final readonly class LicenceRegister
{
    /** The extensions of the font files a scan reads, lower case. */
    private const array EXTENSIONS = ['ttf', 'otf', 'ttc', 'otc', 'woff', 'woff2'];

    /** @var array<string, LicenceRecord> by file, sorted */
    private array $records;

    /**
     * @param list<LicenceRecord> $records
     */
    public function __construct(array $records = [])
    {
        $byFile = [];

        foreach ($records as $record) {
            $byFile[$record->file] = $record;
        }

        ksort($byFile, \SORT_STRING);
        $this->records = $byFile;
    }

    /**
     * Records every font file under a directory, by its path relative to it with `/` between folders.
     *
     * @param (Closure(string, int): (string|false))|null $brotli the decompressor WOFF2 files need
     */
    public static function scan(string $directory, ?Closure $brotli = null): self
    {
        $records = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        $root = \strlen(rtrim($directory, '/\\')) + 1;

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (\in_array(strtolower($file->getExtension()), self::EXTENSIONS, true)) {
                $path = str_replace(\DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), $root));
                $records[] = LicenceRecord::of($path, FontFile::fromFile($file->getPathname(), $brotli));
            }
        }

        return new self($records);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidLicenceRegister::because('The licence register is not valid JSON: '.$jsonException->getMessage());
        }

        $fonts = Field::list(\is_array($data) ? $data : [], 'fonts');

        return new self(array_map(
            static fn (mixed $record): LicenceRecord => \is_array($record) ? LicenceRecord::fromArray($record) : throw InvalidLicenceRegister::because('Each record must be an object.'),
            $fonts,
        ));
    }

    public function toJson(): string
    {
        return json_encode(
            ['fonts' => $this->records()],
            \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        )."\n";
    }

    /**
     * The register with a record added, or put in place of the record of the same file.
     */
    public function with(LicenceRecord $record): self
    {
        return new self([...$this->records(), $record]);
    }

    public function get(string $file): ?LicenceRecord
    {
        return $this->records[$file] ?? null;
    }

    /**
     * @return list<LicenceRecord> sorted by file
     */
    public function records(): array
    {
        return array_values($this->records);
    }

    /**
     * The files whose record differs between two registers — present in one only, or with another SHA-256 —, sorted:
     * compare the register kept with a new scan to find the fonts whose licence must be checked again.
     *
     * @return list<string>
     */
    public function changedFiles(self $other): array
    {
        $files = array_keys($this->records + $other->records);
        $changed = array_filter(
            $files,
            fn (string|int $file): bool => ($this->records[$file]->sha256 ?? null) !== ($other->records[$file]->sha256 ?? null),
        );
        sort($changed, \SORT_STRING);

        return array_map(strval(...), $changed);
    }
}
