<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Csv;

use Phil\ImportWizard\Csv\CsvDialect;
use Phil\ImportWizard\Csv\CsvReader;
use Phil\ImportWizard\Exception\SourceRefused;
use Phil\ImportWizard\Source\SourceLimits;
use Phil\ImportWizard\Source\SourceRow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvReader::class)]
#[CoversClass(SourceRow::class)]
final class CsvReaderTest extends TestCase
{
    /**
     * @return list<array{int, list<string>, bool}>
     */
    private function read(string $csv, CsvDialect $dialect = new CsvDialect(), SourceLimits $limits = new SourceLimits()): array
    {
        return array_map(static fn (SourceRow $row): array => [$row->line, $row->cells, $row->malformed], CsvReader::parse($csv, $dialect, $limits));
    }

    public function testItReadsPlainRecords(): void
    {
        self::assertSame([[1, ['a', 'b'], false], [2, ['c', 'd'], false]], $this->read("a,b\nc,d\n"));
        self::assertSame([[1, ['a', 'b'], false], [2, ['c', 'd'], false]], $this->read("a,b\r\nc,d"));
        self::assertSame([[1, ['a'], false], [2, ['b'], false], [3, ['c'], false]], $this->read("a\rb\r\nc"));
    }

    public function testItReadsEmptyCellsAndLines(): void
    {
        self::assertSame([[1, ['', '', ''], false]], $this->read(',,'));
        self::assertSame([[1, ['a', ''], false]], $this->read("a,\n"));
        self::assertSame([[1, ['a'], false], [2, [''], false], [3, ['b'], false]], $this->read("a\n\nb"));
        self::assertSame([], $this->read(''));
    }

    public function testItReadsQuotedCellsWithDelimitersQuotesAndLineBreaks(): void
    {
        self::assertSame(
            [[1, ['a,b', 'say "hi"', "two\nlines"], false], [3, ['next', ''], false]],
            $this->read("\"a,b\",\"say \"\"hi\"\"\",\"two\nlines\"\nnext,\"\""),
        );
    }

    public function testThePhysicalLineCountsEveryKindOfLineBreakInsideAQuotedCell(): void
    {
        $rows = $this->read("\"a\r\nb\rc\nd\",x\ne\n\"\"\"\r\n\"\nf");

        self::assertSame([1, 5, 6, 8], array_column($rows, 0));
        self::assertSame("a\r\nb\rc\nd", $rows[0][1][0]);
        self::assertSame("\"\r\n", $rows[2][1][0]);
    }

    public function testTextAfterAClosingQuoteMarksTheRecordMalformed(): void
    {
        self::assertSame([[1, ['ab c', 'd'], true], [2, ['e'], false]], $this->read("\"ab\" c,d\ne"));
        self::assertSame([[1, ['a', 'b'], false]], $this->read('"a","b"'));
    }

    public function testAQuoteInsideAnUnquotedCellIsText(): void
    {
        self::assertSame([[1, ['5" disk', 'b'], false]], $this->read('5" disk,b'));
    }

    public function testItRefusesAQuoteLeftOpen(): void
    {
        $this->expectException(SourceRefused::class);
        $this->expectExceptionMessageIsOrContains('The quote opened on line 2 is never closed.');

        $this->read("a\n\"b\nc");
    }

    public function testItReadsTheDelimiterAndTheQuoteOfTheDialect(): void
    {
        self::assertSame([[1, ['a,b', 'c;d'], false]], $this->read("a,b;'c;d'", new CsvDialect(';', "'")));
        self::assertSame([[1, ['a', 'b c'], false]], $this->read("a\tb c", new CsvDialect("\t")));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function guarded(): iterable
    {
        yield 'equals' => ["'=SUM(A1)", '=SUM(A1)'];
        yield 'plus' => ["'+1", '+1'];
        yield 'minus' => ["'-x", '-x'];
        yield 'at' => ["'@x", '@x'];
        yield 'tab' => ["'\tx", "\tx"];
        yield 'carriage return' => ["\"'\rx\"", "\rx"];
        yield 'an apostrophe kept before a letter' => ["'tis", "'tis"];
        yield 'a lone apostrophe kept' => ["'", "'"];
        yield 'two apostrophes kept' => ["''", "''"];
    }

    #[DataProvider('guarded')]
    public function testItDropsTheApostropheOfAGuardedFormula(string $csv, string $cell): void
    {
        self::assertSame($cell, $this->read($csv)[0][1][0]);
    }

    public function testItKeepsTheApostropheWithoutTheGuard(): void
    {
        self::assertSame("'=1", $this->read("'=1", new CsvDialect(guardFormulas: false))[0][1][0]);
    }

    public function testItRefusesARecordWithTooManyCells(): void
    {
        self::assertCount(3, $this->read('a,b,c', limits: new SourceLimits(maxColumns: 3))[0][1]);

        $this->expectException(SourceRefused::class);
        $this->expectExceptionMessageIsOrContains('The record on line 2 has more than 3 cells.');

        $this->read("a\na,b,c,d", limits: new SourceLimits(maxColumns: 3));
    }

    public function testItRefusesTooManyRecordsAfterTheHeader(): void
    {
        self::assertCount(3, $this->read("h\n1\n2", limits: new SourceLimits(maxRows: 2)));

        $this->expectException(SourceRefused::class);
        $this->expectExceptionMessageIsOrContains('The file has more than 2 rows.');

        $this->read("h\n1\n2\n3", limits: new SourceLimits(maxRows: 2));
    }

    public function testABlankRecordIsSeen(): void
    {
        self::assertTrue(new SourceRow(1, ['', ' ', "\t"])->isBlank());
        self::assertFalse(new SourceRow(1, ['', 'a'])->isBlank());
        self::assertFalse(new SourceRow(1, ['a', ''])->isBlank());
    }
}
