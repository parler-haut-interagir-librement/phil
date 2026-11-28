<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit;

use DateTimeImmutable;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Headings;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Row;
use Phil\Spreadsheet\RowRange;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Column::class)]
#[CoversClass(Sheet::class)]
#[CoversClass(Workbook::class)]
#[CoversClass(RowRange::class)]
#[CoversClass(ReadLimits::class)]
#[CoversClass(Row::class)]
#[CoversClass(WriteOptions::class)]
final class ModelTest extends TestCase
{
    public function testAColumnHeadsWithItsLabelOrItsCode(): void
    {
        $column = new Column('q1', 'Question 1');

        self::assertSame('Question 1', $column->heading(Headings::Labels));
        self::assertSame('q1', $column->heading(Headings::Codes));
        self::assertSame('q1', new Column('q1')->heading(Headings::Labels));
    }

    public function testAColumnWidthLiesBetweenOneAndTwoHundredFiftyFive(): void
    {
        self::assertSame(1.0, new Column('a', width: 1)->width);
        self::assertSame(255.0, new Column('a', width: 255)->width);

        foreach ([0.99, 255.01] as $width) {
            try {
                new Column('a', width: $width);
                self::fail('The width is refused.');
            } catch (InvalidSpreadsheet $exception) {
                self::assertSame('The width of the column "a" lies between 1 and 255 characters.', $exception->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('é', 32)];
        yield 'bracket' => ['a[b'];
        yield 'closing bracket' => ['a]b'];
        yield 'colon' => ['a:b'];
        yield 'star' => ['a*b'];
        yield 'question mark' => ['a?b'];
        yield 'slash' => ['a/b'];
        yield 'backslash' => ['a\b'];
        yield 'control' => ["a\nb"];
        yield 'delete' => ["a\x7Fb"];
        yield 'leading apostrophe' => ["'ab"];
        yield 'trailing apostrophe' => ["ab'"];
        yield 'reserved' => ['History'];
        yield 'not UTF-8' => ["a\xFFb"];
    }

    #[DataProvider('invalidNames')]
    public function testASheetNameIsOneASpreadsheetAllows(string $name): void
    {
        $this->expectException(InvalidSpreadsheet::class);
        $this->expectExceptionMessageMatches('/is not a sheet name: 1 to 31 characters, none of \[ \] : \* \? \/ \\\\, no apostrophe at either end\.$/');

        new Sheet($name, []);
    }

    public function testASheetNameCanBeLongAndAccented(): void
    {
        $name = str_repeat('é', 31);

        self::assertSame($name, new Sheet($name, [])->name);
        self::assertSame("a'b", new Sheet("a'b", [])->name);
        self::assertSame('Histories', new Sheet('Histories', [])->name);
    }

    public function testTheMessageOfARefusedNameIsCutAndUtf8(): void
    {
        try {
            new Sheet(str_repeat('a', 50)."\xFF", []);
            self::fail('The name is refused.');
        } catch (InvalidSpreadsheet $invalidSpreadsheet) {
            self::assertStringStartsWith('"'.str_repeat('a', 40).'" is not', $invalidSpreadsheet->getMessage());
        }
    }

    public function testASheetNameIsMadeFromAnyTitle(): void
    {
        self::assertSame('Q1  2026', Sheet::nameFrom('Q1: 2026'));
        self::assertSame('a b', Sheet::nameFrom("'a b'"));
        self::assertSame(str_repeat('x', 31), Sheet::nameFrom(str_repeat('x', 40)));
        self::assertSame('abc', Sheet::nameFrom(str_repeat(' ', 0)."abc\x7F"));
        self::assertSame('Sheet1', Sheet::nameFrom(''));
        self::assertSame('Sheet1', Sheet::nameFrom('[]'));
        self::assertSame('Sheet1', Sheet::nameFrom('history'));
        self::assertSame('ab', Sheet::nameFrom(str_repeat(' ', 29)."'ab"));
    }

    public function testAWorkbookHoldsSheetsOfDistinctNames(): void
    {
        $workbook = new Workbook(new Sheet('One', []), new Sheet('Two', []));

        self::assertSame(['One', 'Two'], array_map(static fn (Sheet $sheet): string => $sheet->name, $workbook->sheets));
        self::assertSame('Single', Workbook::single('Single', [['a']], [new Column('a')])->sheets[0]->name);
        self::assertCount(1, Workbook::single('Single', [], [new Column('a')])->sheets[0]->columns);
    }

    public function testAWorkbookRefusesNoSheetAndTwoSheetsOfTheSameName(): void
    {
        try {
            new Workbook();
            self::fail('No sheet is refused.');
        } catch (InvalidSpreadsheet $invalidSpreadsheet) {
            self::assertSame('A workbook holds at least one sheet.', $invalidSpreadsheet->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('Two sheets are named "ÉTÉ".');

        new Workbook(new Sheet('été', []), new Sheet('ÉTÉ', []));
    }

    public function testARangeStartsAtOneAndEndsAfterItsStart(): void
    {
        self::assertSame([1, null], [new RowRange()->first, new RowRange()->last]);
        self::assertSame([3, 3], [new RowRange(3, 3)->first, new RowRange(3, 3)->last]);

        try {
            new RowRange(0);
            self::fail('A first row of 0 is refused.');
        } catch (InvalidSpreadsheet $invalidSpreadsheet) {
            self::assertSame('The first row of a range is 1 or more.', $invalidSpreadsheet->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The last row of a range comes at or after its first.');

        new RowRange(3, 2);
    }

    public function testEveryReadLimitIsOneOrMore(): void
    {
        self::assertSame(1, new ReadLimits(1, 1, 1, 1, 1, 1, 1)->maxCells);
        self::assertSame(16_384, new ReadLimits()->maxColumns);

        foreach (['maxFileBytes', 'maxEntries', 'maxPartBytes', 'maxSharedStringsBytes', 'maxRows', 'maxColumns', 'maxCells'] as $limit) {
            try {
                new ReadLimits(...[$limit => 0]);
                self::fail($limit.' of 0 is refused.');
            } catch (InvalidSpreadsheet $exception) {
                self::assertSame('The read limit '.$limit.' is 1 or more.', $exception->getMessage());
            }
        }
    }

    public function testARowGivesItsCellsAndTheirValues(): void
    {
        $date = new DateTimeImmutable('2026-11-21');
        $row = new Row(4, [Cell::text('a'), Cell::empty(), Cell::number(2), Cell::dateTime($date)]);

        self::assertSame(4, $row->number);
        self::assertSame('a', $row->cell(0)->value);
        self::assertTrue($row->cell(9)->isEmpty());
        self::assertEquals(['a', null, 2, $date], $row->values());
    }

    public function testTheOptionsGuardFormulasAndWriteLabelsByDefault(): void
    {
        $options = new WriteOptions();

        self::assertTrue($options->guardFormulas);
        self::assertSame(Headings::Labels, $options->headings);
        self::assertSame(1, $options->range->first);
    }
}
