<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit;

use DateTimeImmutable;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Csv\CsvDialect;
use Phil\Spreadsheet\Csv\CsvReader;
use Phil\Spreadsheet\Csv\CsvWriter;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\Spreadsheets;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Tests\Support\Workbooks;
use Phil\Spreadsheet\Workbook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The factory, and the files each writer writes read back by the reader of the same format.
 */
#[CoversClass(Spreadsheets::class)]
final class SpreadsheetsTest extends TestCase
{
    use TemporaryFiles;

    public function testItGivesTheWriterAndTheReaderOfAFormat(): void
    {
        $spreadsheets = new Spreadsheets();

        foreach (Format::cases() as $format) {
            self::assertSame($format, $spreadsheets->writer($format)->format());
            self::assertSame($format, $spreadsheets->reader($format)->format());
            self::assertSame($format, $spreadsheets->writer('export.'.$format->extension())->format());
            self::assertSame($format, $spreadsheets->reader($format->mediaType())->format());
        }

        $this->expectException(InvalidSpreadsheet::class);

        $spreadsheets->reader('report.pdf');
    }

    public function testTheReadersHaveTheLimitsAndTheCsvFilesTheDialect(): void
    {
        $spreadsheets = new Spreadsheets(new ReadLimits(maxRows: 1), CsvDialect::semicolon());
        $path = $this->path('data.csv');
        $spreadsheets->writeFile(Workbook::single('Data', [['a;b', 1]]), $path);

        self::assertSame("\u{FEFF}\"a;b\";1\r\n", file_get_contents($path));
        self::assertEquals([new CsvWriter(CsvDialect::semicolon())], [$spreadsheets->writer(Format::Csv)]);
        self::assertEquals([new CsvReader(CsvDialect::semicolon(), new ReadLimits(maxRows: 1))], [$spreadsheets->reader(Format::Csv)]);

        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The number of the row 2 goes over the limit of 1.');

        iterator_to_array($spreadsheets->reader('csv')->rows($this->file('two.csv', "a\nb")));
    }

    public function testTheWritersPutTheirTemporaryFilesInTheDirectoryGiven(): void
    {
        $spreadsheets = new Spreadsheets(temporaryDirectory: $this->folder().'/missing');

        foreach ([Format::Xlsx, Format::Ods] as $format) {
            try {
                $spreadsheets->writeFile(Workbook::single('Data', []), $this->path('out.'.$format->extension()));
                self::fail('The directory is missing.');
            } catch (WriteFailed $exception) {
                self::assertStringStartsWith('No temporary file can be made in', $exception->getMessage());
            }
        }
    }

    public function testWriteFileTakesTheFormatOfTheExtensionUnlessOneIsGiven(): void
    {
        $spreadsheets = new Spreadsheets();
        $path = $this->path('data.txt');
        $spreadsheets->writeFile(Workbook::single('Data', [['a']]), $path, Format::Ods);

        self::assertSame("PK\x03\x04", substr((string) file_get_contents($path), 0, 4));

        try {
            $spreadsheets->writeFile(Workbook::single('Data', []), $this->path('data.txt'));
            self::fail('No format.');
        } catch (InvalidSpreadsheet) {
        }

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('The file cannot be opened for writing.');

        $spreadsheets->writeFile(Workbook::single('Data', []), $this->folder().'/missing/data.csv');
    }

    /**
     * @return iterable<array{Format}>
     */
    public static function formats(): iterable
    {
        yield 'xlsx' => [Format::Xlsx];
        yield 'ods' => [Format::Ods];
    }

    #[DataProvider('formats')]
    public function testAWorkbookReadsBackAsItWasWritten(Format $format): void
    {
        $rows = [
            ['00123', 'SKU-0042', 42, -0.125, 1.0e20, true, false],
            [Cell::date(new DateTimeImmutable('2026-11-21')), new DateTimeImmutable('2026-11-21 14:30:05'), null, '=1+1', "two\nlines", "  spaced\tout  ", 'été & <b>'],
            [],
            ['_x0041_ stays', 'last'],
        ];
        $path = $this->path('book.'.$format->extension());
        $spreadsheets = new Spreadsheets();
        $spreadsheets->writeFile(new Workbook(new Sheet('Answers', $rows, [new Column('code', 'Code'), new Column('sku', 'SKU', 12)]), new Sheet('Ünïcode', [['x']])), $path);

        $reader = $spreadsheets->reader($format);

        self::assertSame(['Answers', 'Ünïcode'], $reader->sheetNames($path));
        self::assertSame([
            1 => ['text:Code', 'text:SKU'],
            2 => ['text:00123', 'text:SKU-0042', 'number:42', 'number:-0.125', 'number:1.0E+20', 'boolean:true', 'boolean:false'],
            3 => ['date:2026-11-21T00:00:00.000+00:00', 'date-time:2026-11-21T14:30:05.000+00:00', 'empty:', 'text:=1+1', "text:two\nlines", "text:  spaced\tout  ", 'text:été & <b>'],
            5 => ['text:_x0041_ stays', 'text:last'],
        ], Workbooks::dump($reader->rows($path)));
        self::assertSame([1 => ['text:x']], Workbooks::dump($reader->rows($path, 'Ünïcode')));
    }

    public function testACsvFileReadsBackAsText(): void
    {
        $path = $this->path('data.csv');
        $spreadsheets = new Spreadsheets();
        $spreadsheets->writeFile(Workbook::single('Data', [['00123', 12, true, "a,\"b\"\nc", null, 'x']]), $path);

        self::assertSame([1 => ['text:00123', 'text:12', 'text:TRUE', "text:a,\"b\"\nc", 'empty:', 'text:x']], Workbooks::dump($spreadsheets->reader($path)->rows($path)));
    }
}
