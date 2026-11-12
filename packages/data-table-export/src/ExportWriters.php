<?php

declare(strict_types=1);

namespace Phil\DataTableExport;

use Phil\DataTableExport\Contract\ExportWriter;
use Phil\DataTableExport\Csv\CsvWriter;
use Phil\DataTableExport\Exception\ExportRefused;
use Phil\DataTableExport\Xlsx\XlsxWriter;

/**
 * The writers an application offers, one per format, in the order they are offered.
 */
final readonly class ExportWriters
{
    /** @var array<string, ExportWriter> by format */
    private array $writers;

    /**
     * @param iterable<ExportWriter> $writers
     *
     * @throws ExportRefused when two writers write the same format
     */
    public function __construct(iterable $writers)
    {
        $byFormat = [];

        foreach ($writers as $writer) {
            $format = $writer->format()->value;

            if (isset($byFormat[$format])) {
                throw ExportRefused::because(\sprintf('Two writers write the format "%s".', $format));
            }

            $byFormat[$format] = $writer;
        }

        $this->writers = $byFormat;
    }

    /**
     * CSV and XLSX, with their defaults.
     *
     * @param string|null $directory where XLSX writes its temporary files, the system's temporary directory when null
     */
    public static function defaults(?string $directory = null): self
    {
        return new self([new CsvWriter(), new XlsxWriter($directory)]);
    }

    /**
     * @return list<Format>
     */
    public function formats(): array
    {
        return array_map(Format::from(...), array_keys($this->writers));
    }

    public function has(Format $format): bool
    {
        return isset($this->writers[$format->value]);
    }

    /**
     * @throws ExportRefused when no writer writes the format
     */
    public function get(Format $format): ExportWriter
    {
        return $this->writers[$format->value] ?? throw ExportRefused::because(\sprintf('No writer writes the format "%s".', $format->value));
    }

    /**
     * @param resource $stream open for writing
     *
     * @throws ExportRefused when no writer writes the format, or the writer refuses the sheet
     */
    public function write(Format $format, Sheet $sheet, mixed $stream): void
    {
        $this->get($format)->write($sheet, $stream);
    }
}
