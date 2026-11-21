<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Csv;

use Phil\ImportWizard\Contract\RowSource;
use Phil\ImportWizard\Exception\SourceRefused;
use Phil\ImportWizard\Source\SourceLimits;
use Phil\ImportWizard\Source\SourceRow;

/**
 * The rows of a CSV file, read within its limits: the byte order mark dropped, the text checked in its encoding and
 * converted to UTF-8, the first record that is not blank taken as the header.
 */
final readonly class CsvRowSource implements RowSource
{
    private const string BOM = "\u{FEFF}";

    /** @var list<string> */
    private array $headers;

    /** @var list<SourceRow> */
    private array $rows;

    private function __construct(string $content, CsvDialect $dialect, SourceLimits $limits)
    {
        if (\strlen($content) > $limits->maxBytes) {
            throw SourceRefused::because(\sprintf('The file is larger than %d bytes.', $limits->maxBytes));
        }

        if (str_starts_with($content, self::BOM)) {
            $content = substr($content, \strlen(self::BOM));
        } elseif ('UTF-8' !== $dialect->encoding) {
            $converted = mb_convert_encoding($content, 'UTF-8', $dialect->encoding);
            $content = false === $converted ? throw SourceRefused::because('The file cannot be converted to UTF-8.') : $converted;
        }

        if (!mb_check_encoding($content, 'UTF-8')) {
            throw SourceRefused::because('The file is not UTF-8: save it as CSV UTF-8, or read it with the encoding it is written in.');
        }

        $records = CsvReader::parse($content, $dialect, $limits);
        $header = null;

        while ([] !== $records && null === $header) {
            $record = array_shift($records);
            $header = $record->isBlank() ? null : $record;
        }

        if (null === $header) {
            throw SourceRefused::because('The file has no header.');
        }

        $this->headers = array_map(trim(...), $header->cells);
        $this->rows = $records;
    }

    /**
     * Reads CSV text; the dialect is detected from its first line when none is given.
     */
    public static function fromString(string $content, ?CsvDialect $dialect = null, SourceLimits $limits = new SourceLimits()): self
    {
        return new self($content, $dialect ?? CsvDialect::detect($content), $limits);
    }

    /**
     * Reads a CSV file, never more than the limit of bytes and one.
     */
    public static function fromFile(string $path, ?CsvDialect $dialect = null, SourceLimits $limits = new SourceLimits()): self
    {
        $content = is_file($path) && is_readable($path) ? file_get_contents($path, false, null, 0, $limits->maxBytes + 1) : false;

        if (false === $content) {
            throw SourceRefused::because('The file cannot be read.');
        }

        return self::fromString($content, $dialect, $limits);
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function rows(): iterable
    {
        return $this->rows;
    }
}
