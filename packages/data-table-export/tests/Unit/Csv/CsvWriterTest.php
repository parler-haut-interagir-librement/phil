<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Tests\Unit\Csv;

use ArrayObject;
use DateTimeImmutable;
use Generator;
use Phil\DataTableExport\Cells;
use Phil\DataTableExport\Csv\CsvWriter;
use Phil\DataTableExport\Exception\ExportRefused;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;
use Phil\DataTableExport\Tests\Unit\Fixture\Level;
use Phil\DataTableExport\Tests\Unit\Fixture\Title;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(CsvWriter::class)]
#[CoversClass(Cells::class)]
final class CsvWriterTest extends TestCase
{
    public function testItWritesEveryKindOfCellAfterRfc4180(): void
    {
        $csv = $this->write(new CsvWriter(byteOrderMark: false), new Sheet('Books', ['Title', 'Pages', 'Read'], [
            ['Dune, the "first"', 412, true],
            [new Title('Hyperion'), 3.25, false],
            [Level::High, null, new DateTimeImmutable('2026-11-12 00:00')],
            ["two\nlines", -0.5, new DateTimeImmutable('2026-11-12 14:30:05')],
        ]));

        self::assertSame(
            "Title,Pages,Read\r\n"
            ."\"Dune, the \"\"first\"\"\",412,1\r\n"
            ."Hyperion,3.25,0\r\n"
            ."high,,2026-11-12\r\n"
            ."\"two\nlines\",-0.5,\"2026-11-12 14:30:05\"\r\n",
            $csv,
        );
    }

    public function testItStartsWithTheByteOrderMarkByDefault(): void
    {
        self::assertSame("\u{FEFF}a\r\n", $this->write(new CsvWriter(), new Sheet('', [], [['a']])));
        self::assertSame(Format::Csv, new CsvWriter()->format());
    }

    public function testItKeepsFormulasAsTextUnlessAskedNotTo(): void
    {
        $sheet = static fn (): Sheet => new Sheet('', ['=HEAD'], [['=1+1', '-3', '@x']]);

        self::assertSame("'=HEAD\r\n'=1+1,-3,'@x\r\n", $this->write(new CsvWriter(byteOrderMark: false), $sheet()));
        self::assertSame("=HEAD\r\n=1+1,-3,@x\r\n", $this->write(new CsvWriter(byteOrderMark: false, guardFormulas: false), $sheet()));
    }

    public function testItWritesWithAnotherSeparator(): void
    {
        self::assertSame("a;\"b;c\"\r\n", $this->write(new CsvWriter(';', false), new Sheet('', [], [['a', 'b;c']])));
    }

    public function testItReadsTheRowsOnceAndAsTheyCome(): void
    {
        $read = new ArrayObject();
        $rows = (static function () use ($read): Generator {
            foreach (['a', 'b'] as $value) {
                $read->append($value);

                yield [$value];
            }
        })();

        self::assertSame("a\r\nb\r\n", $this->write(new CsvWriter(byteOrderMark: false), new Sheet('', [], $rows)));
        self::assertSame(['a', 'b'], $read->getArrayCopy());
    }

    public function testItRefusesASeparatorItCannotWrite(): void
    {
        foreach (['', ';;', '"', "\n", "\r"] as $separator) {
            try {
                new CsvWriter($separator);
                self::fail(\sprintf('The separator "%s" is refused.', addcslashes($separator, "\r\n")));
            } catch (ExportRefused $refused) {
                self::assertSame('The separator of a CSV file is one byte, neither a quote nor an end of line.', $refused->getMessage());
            }
        }
    }

    public function testItRefusesACellOfNoKindItWrites(): void
    {
        $this->expectExceptionObject(ExportRefused::because('A cell holds a stdClass, which no sheet writes: give a scalar, a date, a backed enum or a Stringable.'));

        $this->write(new CsvWriter(), new Sheet('', [], [[new stdClass()]]));
    }

    public function testItSaysWhenTheStreamRefusesTheMark(): void
    {
        $this->expectExceptionObject(ExportRefused::because('The stream refused the CSV file.'));

        $this->writeReadOnly(new CsvWriter(), new Sheet('', [], []));
    }

    public function testItSaysWhenTheStreamRefusesALine(): void
    {
        $this->expectExceptionObject(ExportRefused::because('The stream refused a line of the CSV file.'));

        $this->writeReadOnly(new CsvWriter(byteOrderMark: false), new Sheet('', ['a'], []));
    }

    private function write(CsvWriter $writer, Sheet $sheet): string
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        $writer->write($sheet, $stream);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    private function writeReadOnly(CsvWriter $writer, Sheet $sheet): void
    {
        $stream = fopen(__FILE__, 'r');
        self::assertIsResource($stream);

        try {
            $writer->write($sheet, $stream);
        } finally {
            fclose($stream);
        }
    }
}
