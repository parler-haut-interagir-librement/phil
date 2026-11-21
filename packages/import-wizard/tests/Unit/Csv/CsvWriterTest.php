<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Unit\Csv;

use Phil\ImportWizard\Csv\CsvDialect;
use Phil\ImportWizard\Csv\CsvReader;
use Phil\ImportWizard\Csv\CsvWriter;
use Phil\ImportWizard\Source\SourceLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvWriter::class)]
final class CsvWriterTest extends TestCase
{
    public function testItWritesRowsWithAByteOrderMarkAndCrlf(): void
    {
        self::assertSame("\u{FEFF}a,b\r\nc,\r\n", CsvWriter::write([['a', 'b'], ['c', '']]));
        self::assertSame("a;b\r\n", CsvWriter::write([['a', 'b']], new CsvDialect(';', byteOrderMark: false)));
        self::assertSame("\u{FEFF}", CsvWriter::write([]));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function cells(): iterable
    {
        yield 'plain' => ['abc', 'abc'];
        yield 'empty' => ['', ''];
        yield 'delimiter' => ['a,b', '"a,b"'];
        yield 'quote' => ['5" disk', '"5"" disk"'];
        yield 'line feed' => ["a\nb", "\"a\nb\""];
        yield 'carriage return' => ["a\rb", "\"a\rb\""];
        yield 'leading space' => [' a', '" a"'];
        yield 'trailing space' => ['a ', '"a "'];
        yield 'formula' => ['=SUM(A1)', "'=SUM(A1)"];
        yield 'plus' => ['+x', "'+x"];
        yield 'minus' => ['-x', "'-x"];
        yield 'at' => ['@x', "'@x"];
        yield 'tab' => ["\tx", "'\tx"];
        yield 'carriage return first' => ["\rx", "\"'\rx\""];
        yield 'negative number' => ['-12', '-12'];
        yield 'signed decimal' => ['+1.5', '+1.5'];
        yield 'decimal with a comma' => ['-1,5', '"-1,5"'];
        yield 'number then text' => ['-12x', "'-12x"];
        yield 'text then number' => ['-x12', "'-x12"];
        yield 'equals in the middle' => ['a=b', 'a=b'];
    }

    #[DataProvider('cells')]
    public function testItWritesACell(string $cell, string $written): void
    {
        self::assertSame($written."\r\n", CsvWriter::write([[$cell]], new CsvDialect(byteOrderMark: false)));
    }

    public function testItWritesFormulasAsTheyAreWithoutTheGuard(): void
    {
        self::assertSame("=1\r\n", CsvWriter::write([['=1']], new CsvDialect(byteOrderMark: false, guardFormulas: false)));
    }

    public function testItWritesWithTheQuoteOfTheDialect(): void
    {
        self::assertSame("'a,b','it''s'\r\n", CsvWriter::write([['a,b', "it's"]], new CsvDialect(enclosure: "'", byteOrderMark: false)));
    }

    public function testWhatItWritesReadsBackTheSame(): void
    {
        $rows = [['=1', "two\nlines", ' x ', 'say "hi"', '-5', '@a'], ['', 'b', 'c', 'd', 'e', 'f']];

        foreach ([new CsvDialect(byteOrderMark: false), new CsvDialect(';', "'", byteOrderMark: false)] as $dialect) {
            $read = CsvReader::parse(CsvWriter::write($rows, $dialect), $dialect, new SourceLimits());

            self::assertSame($rows, [$read[0]->cells, $read[1]->cells]);
        }
    }

    public function testANumberWithATrailingLineFeedIsStillGuardedAsAFormula(): void
    {
        self::assertSame("\"'-1\n\"\r\n", CsvWriter::write([["-1\n"]], new CsvDialect(byteOrderMark: false)));
    }
}
