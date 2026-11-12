<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\DataTableExport\Csv\CsvWriter;
use Phil\DataTableExport\Sheet;
use Phil\DataTableExport\Xlsx\XlsxWriter;

/**
 * The hot path of an export: ten thousand rows of five cells — a text, an integer, a float, a boolean and a date —
 * written to memory, as CSV and as XLSX.
 *
 * @Groups({"export"})
 */
final class ExportBench
{
    private const int ROWS = 10000;

    /**
     * @Revs(5)
     *
     * @Iterations(5)
     */
    public function benchCsv(): void
    {
        $this->write(new CsvWriter());
    }

    /**
     * @Revs(5)
     *
     * @Iterations(5)
     */
    public function benchXlsx(): void
    {
        $this->write(new XlsxWriter());
    }

    private function write(CsvWriter|XlsxWriter $writer): void
    {
        $stream = fopen('php://temp', 'w+');
        \assert(\is_resource($stream));
        $writer->write(new Sheet('Books', ['Title', 'Pages', 'Price', 'Read', 'On'], $this->rows()), $stream);
        fclose($stream);
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function rows(): Generator
    {
        $day = new DateTimeImmutable('2026-11-12 10:30 UTC');

        for ($row = 0; $row < self::ROWS; ++$row) {
            yield ['Book number '.$row, $row, $row / 7, 0 === $row % 2, $day];
        }
    }
}
