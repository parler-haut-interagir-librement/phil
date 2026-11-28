<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Spreadsheet\Csv\CsvReader;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\Archive;
use Phil\Spreadsheet\Internal\BudgetFilter;
use Phil\Spreadsheet\Internal\BufferedFile;
use Phil\Spreadsheet\Internal\ByteBudget;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\Xlsx\CellReference;
use Phil\Spreadsheet\Xlsx\ExcelDate;
use Phil\Spreadsheet\Xlsx\NumberFormats;
use Phil\Spreadsheet\Xlsx\XlsxText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * The edges of the values, the limits and the messages: each default, each cut, each boundary at its exact place.
 */
#[CoversClass(ReadLimits::class)]
#[CoversClass(Format::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(Workbook::class)]
#[CoversClass(Archive::class)]
#[CoversClass(BufferedFile::class)]
#[CoversClass(BudgetFilter::class)]
#[CoversClass(ByteBudget::class)]
#[CoversClass(SafeXmlReader::class)]
#[CoversClass(Values::class)]
#[CoversClass(ExcelDate::class)]
#[CoversClass(XlsxText::class)]
#[CoversClass(NumberFormats::class)]
#[CoversClass(CellReference::class)]
#[CoversClass(CsvReader::class)]
final class BoundariesTest extends TestCase
{
    use TemporaryFiles;

    public function testTheLimitsHaveTheirDefaults(): void
    {
        $limits = new ReadLimits();

        self::assertSame(268_435_456, $limits->maxFileBytes);
        self::assertSame(1_000, $limits->maxEntries);
        self::assertSame(1_073_741_824, $limits->maxPartBytes);
        self::assertSame(134_217_728, $limits->maxSharedStringsBytes);
        self::assertSame(1_048_576, $limits->maxRows);
        self::assertSame(16_384, $limits->maxColumns);
        self::assertSame(50_000_000, $limits->maxCells);
    }

    public function testASheetNameInAMessageIsCutToFortyCharacters(): void
    {
        $name = 'a'.str_repeat('é', 60);

        try {
            new Sheet($name, []);
            self::fail('The name is too long.');
        } catch (InvalidSpreadsheet $invalidSpreadsheet) {
            self::assertStringStartsWith('"a'.str_repeat('é', 39).'" is not', $invalidSpreadsheet->getMessage());
        }
    }

    public function testAFormatNameInAMessageIsCutToAHundredCharacters(): void
    {
        try {
            Format::detect('a'.str_repeat('é', 150));
            self::fail('No format is named so.');
        } catch (InvalidSpreadsheet $invalidSpreadsheet) {
            self::assertSame('"a'.str_repeat('é', 99).'" names none of the formats csv, xlsx and ods.', $invalidSpreadsheet->getMessage());
        }
    }

    public function testAMediaTypeIsTrimmedBeforeItsParameters(): void
    {
        self::assertSame(Format::Csv, Format::detect('text/csv ; charset=utf-8'));
    }

    public function testASheetNameIsMadeFromATitle(): void
    {
        self::assertSame(str_repeat('é', 31), Sheet::nameFrom(str_repeat('é', 40)));
        self::assertSame(str_repeat('a', 30), Sheet::nameFrom(str_repeat('a', 30)."'b"));
        self::assertSame('Sheet1', Sheet::nameFrom("''"));
    }

    public function testTheSheetsOfAWorkbookAreAList(): void
    {
        $workbook = new Workbook(...['x' => new Sheet('One', []), 'y' => new Sheet('Two', [])]);

        self::assertSame([0, 1], array_keys($workbook->sheets));
    }

    public function testACsvSheetNameInAMessageIsCutToAHundredCharacters(): void
    {
        $path = $this->file('book.csv', "a\n");

        try {
            iterator_to_array(new CsvReader()->rows($path, 'a'.str_repeat('é', 150)));
            self::fail('The file has no such sheet.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The CSV file has no sheet "a'.str_repeat('é', 99).'".', $readRefused->getMessage());
        }
    }

    public function testAnEntryAndATargetInAMessageAreCutToAHundredCharacters(): void
    {
        $name = '../a'.str_repeat('é', 150);
        $path = $this->zip('bad.zip', [$name => 'x']);

        try {
            Archive::open($path, new ReadLimits());
            self::fail('The entry points outside the root.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The entry "../a'.str_repeat('é', 96).'" of the archive points outside its root.', $readRefused->getMessage());
        }

        try {
            Archive::resolve('', '../a'.str_repeat('é', 150));
            self::fail('The target points outside the archive.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The target "../a'.str_repeat('é', 96).'" points outside the archive.', $readRefused->getMessage());
        }
    }

    public function testAnArchiveCannotBeUsedOnceClosed(): void
    {
        $archive = Archive::open($this->zip('good.zip', ['a' => 'x']), new ReadLimits());
        self::assertTrue($archive->has('a'));
        $archive->close();

        $this->expectException(ValueError::class);
        $archive->has('a');
    }

    public function testABufferedFileReleasesItsHandleWhenClosed(): void
    {
        $before = \count(get_resources('stream'));
        $file = new BufferedFile($this->path('buffered'));
        self::assertCount($before + 1, get_resources('stream'));
        $file->write('x');
        $file->close();

        self::assertCount($before, get_resources('stream'));
        self::assertSame('x', file_get_contents($this->path('buffered')));
    }

    public function testAnXmlReaderReleasesItsStreamWhenClosed(): void
    {
        $before = \count(get_resources('stream'));
        $reader = SafeXmlReader::fromString('<a/>', 'part', 100);
        self::assertTrue($reader->read());
        $reader->close();

        self::assertCount($before, get_resources('stream'));
    }

    public function testAPartOverItsBudgetIsRefusedAtOnce(): void
    {
        $reader = SafeXmlReader::fromString('<a><b/></a>', 'part', 3);

        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The size of the part "part" goes over the limit of 3.');
        $reader->read();
    }

    public function testAPartThatEndsBeforeItsRootIsRefused(): void
    {
        $reader = SafeXmlReader::fromString('<a><b>text', 'part', 100);

        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The part "part" is not well formed.');

        while ($reader->read()) {
        }
    }

    public function testAnAttributeIsTheFirstOfThatLocalName(): void
    {
        $reader = SafeXmlReader::fromString('<a xmlns:x="urn:x" xmlns:y="urn:y" x:id="1" y:id="2"/>', 'part', 200);
        self::assertTrue($reader->read());

        self::assertSame('1', $reader->attribute('id'));
    }

    public function testADateTimeKeepsItsFractionOfASecond(): void
    {
        $cell = Values::isoDate('2024-01-02T03:04:05.5');
        self::assertNotNull($cell);
        self::assertInstanceOf(DateTimeImmutable::class, $cell->value);
        self::assertSame('500000', $cell->value->format('u'));

        $cell = Values::isoDate('2024-01-02T03:04:05.123456789Z');
        self::assertNotNull($cell);
        self::assertInstanceOf(DateTimeImmutable::class, $cell->value);
        self::assertSame('123456', $cell->value->format('u'));

        $cell = Values::isoDate('2024-01-02T03:04:05+02:00');
        self::assertNotNull($cell);
        self::assertInstanceOf(DateTimeImmutable::class, $cell->value);
        self::assertSame('+02:00', $cell->value->format('P'));
        self::assertNull(Values::isoDate("2024-01-02\n"));
        self::assertNull(Values::isoDate('2024-01-02T24:00:00'));
        self::assertNull(Values::isoDate('2024-01-02T23:60:00'));
        self::assertNull(Values::isoDate('2024-01-02T23:59:60'));
        self::assertNotNull(Values::isoDate('2024-01-02T23:59:59'));
    }

    public function testASerialNumberIsAnIntegerForAWholeDay(): void
    {
        $utc = new DateTimeZone('UTC');

        self::assertSame(45293, ExcelDate::serial(new DateTimeImmutable('2024-01-02', $utc)));
        self::assertSame((3_913_315_200 + 0.5) / 86400, ExcelDate::serial(new DateTimeImmutable('2024-01-02 00:00:00.5', $utc)));
        self::assertSame(100 + 100 / 86400, ExcelDate::serial(new DateTimeImmutable('1900-04-09 00:01:40', $utc)));
        self::assertSame(99 + 86300 / 86400, ExcelDate::serial(new DateTimeImmutable('1900-04-08 23:58:20', $utc)));
    }

    public function testASerialNumberIsReadToTheMillisecond(): void
    {
        $date = ExcelDate::date(45293 + 0.123 / 86400);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2024-01-02 00:00:00.123000', $date->format('Y-m-d H:i:s.u'));
        self::assertInstanceOf(DateTimeImmutable::class, ExcelDate::date(0.0));
        self::assertNull(ExcelDate::date(-0.5));
    }

    public function testTheEscapesOfATextAreWritten(): void
    {
        self::assertSame('_x0001_', XlsxText::escape("\x01"));
        self::assertSame('_xFFFE_', XlsxText::escape("\u{FFFE}"));
        self::assertSame('_xFFFF_', XlsxText::escape("\u{FFFF}"));
        self::assertSame('_x005F_x0041_', XlsxText::escape('_x0041_'));
        self::assertTrue(XlsxText::fits(str_repeat('a', XlsxText::LENGTH)));
        self::assertFalse(XlsxText::fits(str_repeat('a', XlsxText::LENGTH + 1)));
        self::assertTrue(XlsxText::fits(str_repeat('é', XlsxText::LENGTH)));
        self::assertFalse(XlsxText::fits(str_repeat('é', XlsxText::LENGTH + 1)));
    }

    public function testTheFormatOfADayHasADayOrAYear(): void
    {
        self::assertSame('date', NumberFormats::kind(164, 'dd')?->value);
        self::assertSame('date', NumberFormats::kind(164, 'yy')?->value);
        self::assertSame('date', NumberFormats::kind(164, 'mm')?->value);
        self::assertNull(NumberFormats::kind(164, '0.00'));
    }

    public function testAColumnReferenceEndsWithItsDigits(): void
    {
        self::assertNull(CellReference::column("A1\n"));
        self::assertSame(1, CellReference::column('A1'));
    }
}
