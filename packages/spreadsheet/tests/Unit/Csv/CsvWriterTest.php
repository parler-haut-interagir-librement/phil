<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Csv;

use DateTimeImmutable;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Csv\CsvDialect;
use Phil\Spreadsheet\Csv\CsvWriter;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Headings;
use Phil\Spreadsheet\Internal\FormulaGuard;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Internal\SheetRows;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\RowRange;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvWriter::class)]
#[CoversClass(CsvDialect::class)]
#[CoversClass(FormulaGuard::class)]
#[CoversClass(SheetRows::class)]
#[CoversClass(Output::class)]
#[CoversClass(Values::class)]
final class CsvWriterTest extends TestCase
{
    /**
     * @param iterable<array<mixed>> $rows
     * @param list<Column>           $columns
     */
    private function write(iterable $rows, array $columns = [], WriteOptions $options = new WriteOptions(), CsvDialect $dialect = new CsvDialect()): string
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        new CsvWriter($dialect)->write(Workbook::single('Data', $rows, $columns), $stream, $options);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    public function testItWritesRfc4180WithAByteOrderMark(): void
    {
        self::assertSame(Format::Csv, new CsvWriter()->format());
        self::assertSame("\u{FEFF}a,\"b,c\",\"say \"\"hi\"\"\",\"two\nlines\"\r\n,x\r\n", $this->write([['a', 'b,c', 'say "hi"', "two\nlines"], [null, 'x']]));
    }

    public function testEachTypeHasItsText(): void
    {
        $row = [
            '00123',
            12,
            -0.5,
            1.0e25,
            true,
            false,
            Cell::date(new DateTimeImmutable('2026-11-21 14:30')),
            new DateTimeImmutable('2026-11-21 14:30:05'),
            new DateTimeImmutable('2026-11-21 14:30:05.250'),
        ];

        self::assertSame("00123,12,-0.5,1.0E+25,TRUE,FALSE,2026-11-21,\"2026-11-21 14:30:05\",\"2026-11-21 14:30:05.25\"\n", $this->write([$row], dialect: new CsvDialect(lineEnding: "\n", byteOrderMark: false)));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function formulas(): iterable
    {
        yield ['=1+1'];
        yield ['+1'];
        yield ['-1'];
        yield ['@SUM(A1)'];
        yield ["\tx"];
        yield ["\rx"];
    }

    #[DataProvider('formulas')]
    public function testATextARunningSpreadsheetWouldComputeGetsAnApostrophe(string $text): void
    {
        $dialect = new CsvDialect(byteOrderMark: false);
        $guarded = $this->write([[$text]], dialect: $dialect);
        $unguarded = $this->write([[$text]], options: new WriteOptions(guardFormulas: false), dialect: $dialect);

        self::assertStringContainsString("'".$text, $guarded);
        self::assertStringNotContainsString("'", $unguarded);
        self::assertSame("'".$text, CsvWriter::text(Cell::text($text)));
        self::assertSame($text, CsvWriter::text(Cell::text($text), false));
    }

    public function testOtherTextsAndNumbersAreLeftAlone(): void
    {
        self::assertSame('a=1', CsvWriter::text(Cell::text('a=1')));
        self::assertSame("\0x", CsvWriter::text(Cell::text("\0x")));
        self::assertSame('-1', CsvWriter::text(Cell::number(-1)));
        self::assertSame('', CsvWriter::text(Cell::empty()));
    }

    public function testTheHeadingsAreTheLabelsTheCodesOrNone(): void
    {
        $columns = [new Column('q1', 'Question 1'), new Column('=q2')];
        $dialect = new CsvDialect(byteOrderMark: false);

        self::assertSame("\"Question 1\",'=q2\r\na,b\r\n", $this->write([['a', 'b']], $columns, dialect: $dialect));
        self::assertSame("q1,'=q2\r\n", $this->write([], $columns, new WriteOptions(Headings::Codes), $dialect));
        self::assertSame("q1,=q2\r\n", $this->write([], $columns, new WriteOptions(Headings::Codes, guardFormulas: false), $dialect));
        self::assertSame("a,b\r\n", $this->write([['a', 'b']], $columns, new WriteOptions(Headings::None), $dialect));
        self::assertSame("a\r\n", $this->write([['a']], [], new WriteOptions(Headings::Labels), $dialect));
    }

    public function testARangeWritesItsRowsAndStopsReading(): void
    {
        $read = new class {
            public int $count = 0;
        };
        $rows = (static function () use ($read): iterable {
            for ($i = 1; $i <= 10; ++$i) {
                ++$read->count;

                yield ['k' => $i];
            }
        })();

        self::assertSame("3\r\n4\r\n", $this->write($rows, options: new WriteOptions(range: new RowRange(3, 4)), dialect: new CsvDialect(byteOrderMark: false)));
        self::assertSame(4, $read->count);
        self::assertSame("9\r\n10\r\n", $this->write([[1], [2], [3], [4], [5], [6], [7], [8], [9], [10]], options: new WriteOptions(range: new RowRange(9)), dialect: new CsvDialect(byteOrderMark: false)));
    }

    public function testASemicolonDialect(): void
    {
        self::assertSame(';', CsvDialect::semicolon()->delimiter);
        self::assertSame("\u{FEFF}\"1;5\";2\r\n", $this->write([['1;5', 2]], dialect: CsvDialect::semicolon()));
    }

    public function testACsvFileHoldsOneSheet(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $this->expectException(InvalidSpreadsheet::class);
        $this->expectExceptionMessageIsOrContains('A CSV file holds one sheet.');

        new CsvWriter()->write(new Workbook(new Sheet('a', []), new Sheet('b', [])), $stream);
    }

    public function testItWritesToAnOpenStreamOnly(): void
    {
        $this->expectException(InvalidSpreadsheet::class);
        $this->expectExceptionMessageIsOrContains('A spreadsheet is written to an open stream.');

        new CsvWriter()->write(Workbook::single('a', []), stream_context_create());
    }

    public function testAClosedStreamIsRefused(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fclose($stream);

        $this->expectException(InvalidSpreadsheet::class);

        new CsvWriter()->write(Workbook::single('a', []), $stream);
    }

    public function testAStreamThatRefusesTheBytesFails(): void
    {
        $stream = fopen('php://memory', 'r');
        self::assertIsResource($stream);

        try {
            new CsvWriter()->write(Workbook::single('a', []), $stream);
            self::fail('The byte order mark is refused.');
        } catch (WriteFailed $writeFailed) {
            self::assertSame('The stream refused the byte order mark.', $writeFailed->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The stream refused a row.');

        new CsvWriter(new CsvDialect(byteOrderMark: false))->write(Workbook::single('a', [['x']]), $stream);
    }

    public function testADialectIsTwoDifferentBytesAndALineEnding(): void
    {
        foreach ([['', '"'], [',,', '"'], [',', ''], [',', '""'], [',', ','], ["\n", '"'], [',', "\r"]] as [$delimiter, $enclosure]) {
            try {
                new CsvDialect($delimiter, $enclosure);
                self::fail('The dialect is refused.');
            } catch (InvalidSpreadsheet $exception) {
                self::assertSame('The delimiter and the enclosure of a CSV dialect are two different bytes, neither a line break.', $exception->getMessage());
            }
        }

        self::assertSame("\n", new CsvDialect(lineEnding: "\n")->lineEnding);

        $this->expectExceptionMessageIsOrContains('The line ending of a CSV dialect is "\r\n" or "\n".');

        new CsvDialect(lineEnding: "\r");
    }
}
