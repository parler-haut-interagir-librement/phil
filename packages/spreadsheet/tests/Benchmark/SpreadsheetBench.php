<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Benchmark;

use DateTimeImmutable;
use Generator;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Spreadsheets;
use Phil\Spreadsheet\Workbook;

/**
 * The hot paths of a large export and of its import: 100,000 rows of ten cells — texts, a product code, an integer, a
 * float, a boolean, a date and empty cells — written as CSV, XLSX and ODS, then read back. The peak memory stays that
 * of a row and a chunk whatever the number of rows: compare mem_peak with a run of fewer rows.
 *
 * @Groups({"spreadsheet"})
 */
final class SpreadsheetBench
{
    private const int ROWS = 100_000;

    private readonly Spreadsheets $spreadsheets;

    private string $path = '';

    public function __construct()
    {
        $this->spreadsheets = new Spreadsheets();
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchWriteCsv(): void
    {
        $this->write(Format::Csv);
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchWriteXlsx(): void
    {
        $this->write(Format::Xlsx);
    }

    /**
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchWriteOds(): void
    {
        $this->write(Format::Ods);
    }

    public function prepareCsv(): void
    {
        $this->prepare(Format::Csv);
    }

    public function prepareXlsx(): void
    {
        $this->prepare(Format::Xlsx);
    }

    public function prepareOds(): void
    {
        $this->prepare(Format::Ods);
    }

    public function remove(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * @BeforeMethods("prepareCsv")
     *
     * @AfterMethods("remove")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchReadCsv(): void
    {
        $this->read(Format::Csv);
    }

    /**
     * @BeforeMethods("prepareXlsx")
     *
     * @AfterMethods("remove")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchReadXlsx(): void
    {
        $this->read(Format::Xlsx);
    }

    /**
     * @BeforeMethods("prepareOds")
     *
     * @AfterMethods("remove")
     *
     * @Revs(1)
     *
     * @Iterations(3)
     */
    public function benchReadOds(): void
    {
        $this->read(Format::Ods);
    }

    private function write(Format $format): void
    {
        $stream = fopen('php://temp', 'w+');
        \assert(\is_resource($stream));
        $this->spreadsheets->writer($format)->write(Workbook::single('Export', $this->rows()), $stream);
        fclose($stream);
    }

    private function prepare(Format $format): void
    {
        $this->path = sys_get_temp_dir().'/phil-spreadsheet-bench-'.bin2hex(random_bytes(4)).'.'.$format->extension();
        $this->spreadsheets->writeFile(Workbook::single('Export', $this->rows()), $this->path);
    }

    private function read(Format $format): void
    {
        $count = 0;

        foreach ($this->spreadsheets->reader($format)->rows($this->path) as $row) {
            $count += \count($row->cells);
        }

        \assert($count > self::ROWS);
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    private function rows(): Generator
    {
        $day = new DateTimeImmutable('2026-11-21');

        for ($row = 0; $row < self::ROWS; ++$row) {
            yield ['Answer number '.$row, 'SKU-00'.$row, $row, $row / 7, 0 === $row % 2, $day, null, 'Some text a person typed', '', 'end'];
        }
    }
}
