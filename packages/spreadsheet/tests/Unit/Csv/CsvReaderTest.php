<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Csv;

use Phil\Spreadsheet\Csv\CsvDialect;
use Phil\Spreadsheet\Csv\CsvReader;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\RowBuilder;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Tests\Support\Workbooks;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvReader::class)]
#[CoversClass(RowBuilder::class)]
final class CsvReaderTest extends TestCase
{
    use TemporaryFiles;

    /**
     * @return array<int, list<string>>
     */
    private function read(string $csv, CsvDialect $dialect = new CsvDialect(), ReadLimits $limits = new ReadLimits()): array
    {
        return Workbooks::dump(new CsvReader($dialect, $limits)->rows($this->file('data.csv', $csv)));
    }

    public function testItReadsEveryCellAsText(): void
    {
        self::assertSame(Format::Csv, new CsvReader()->format());
        self::assertSame(
            [1 => ['text:00123', 'text:12', 'text:TRUE'], 2 => ['text:a,b', 'empty:', 'text:say "hi"', "text:two\nlines"]],
            $this->read("00123,12,TRUE\r\n\"a,b\",,\"say \"\"hi\"\"\",\"two\nlines\"\n"),
        );
    }

    public function testItSkipsTheByteOrderMarkBlankLinesAndEmptyCellsAtTheEnd(): void
    {
        self::assertSame([1 => ['text:a'], 3 => ['empty:', 'text:b'], 5 => ['text:c']], $this->read("\u{FEFF}a,,\n\n,b,\n,,\nc"));
        self::assertSame([1 => ["text:\u{FEFF}a"]], $this->read("\u{FEFF}\u{FEFF}a"));
        self::assertSame([1 => ['text:ab']], $this->read('ab'));
        self::assertSame([], $this->read(''));
    }

    public function testItReadsADialect(): void
    {
        self::assertSame([1 => ['text:1,5', 'text:x;y']], $this->read("1,5;'x;y'", new CsvDialect(';', "'")));
    }

    public function testTheFileIsOneSheetNamedAfterIt(): void
    {
        $path = $this->file('answers.csv', 'a');

        self::assertSame(['answers'], new CsvReader()->sheetNames($path));
        self::assertCount(1, iterator_to_array(new CsvReader()->rows($path, 'answers')));

        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The CSV file has no sheet "other".');

        iterator_to_array(new CsvReader()->rows($path, 'other'));
    }

    public function testAMissingFileIsRefused(): void
    {
        try {
            new CsvReader()->sheetNames($this->path('missing.csv'));
            self::fail('The file is missing.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The file cannot be read.', $readRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The file cannot be read.');

        iterator_to_array(new CsvReader()->rows($this->folder()));
    }

    public function testAFileOverItsSizeIsRefused(): void
    {
        self::assertCount(1, $this->read('abcd', limits: new ReadLimits(maxFileBytes: 4)));

        $this->expectExceptionMessageIsOrContains('The size of the file goes over the limit of 3.');

        $this->read('abcd', limits: new ReadLimits(maxFileBytes: 3));
    }

    public function testARecordOverTheColumnsIsRefused(): void
    {
        self::assertCount(1, $this->read('a,b', limits: new ReadLimits(maxColumns: 2)));

        $this->expectExceptionMessageIsOrContains('The number of cells of the record 2 goes over the limit of 2.');

        $this->read("a\na,b,c", limits: new ReadLimits(maxColumns: 2));
    }

    public function testTheRowsAndCellsAreBounded(): void
    {
        self::assertCount(2, $this->read("a\n\nb", limits: new ReadLimits(maxRows: 3)));

        try {
            $this->read("a\n\nb", limits: new ReadLimits(maxRows: 2));
            self::fail('The third record is past the limit.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The number of the row 3 goes over the limit of 2.', $readRefused->getMessage());
        }

        self::assertCount(2, $this->read("a,,b\nc", limits: new ReadLimits(maxCells: 4)));

        $this->expectExceptionMessageIsOrContains('The number of cells read goes over the limit of 3.');

        $this->read("a,,b\nc", limits: new ReadLimits(maxCells: 3));
    }

    public function testATextThatIsNotUtf8IsRefused(): void
    {
        $this->expectExceptionMessageIsOrContains('The record 2 is not written in UTF-8.');

        $this->read("ok\nok,caf\xE9");
    }
}
