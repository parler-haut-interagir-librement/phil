<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Tests\Unit;

use Phil\DataTableExport\Csv\CsvWriter;
use Phil\DataTableExport\Exception\ExportRefused;
use Phil\DataTableExport\ExportWriters;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;
use Phil\DataTableExport\Xlsx\XlsxWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExportWriters::class)]
#[CoversClass(Format::class)]
final class ExportWritersTest extends TestCase
{
    public function testTheDefaultsWriteCsvAndXlsx(): void
    {
        $writers = ExportWriters::defaults(sys_get_temp_dir());

        self::assertSame([Format::Csv, Format::Xlsx], $writers->formats());
        self::assertInstanceOf(CsvWriter::class, $writers->get(Format::Csv));
        self::assertInstanceOf(XlsxWriter::class, $writers->get(Format::Xlsx));
        self::assertTrue($writers->has(Format::Xlsx));
    }

    public function testItWritesWithTheWriterOfTheFormat(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        new ExportWriters([new CsvWriter(byteOrderMark: false)])->write(Format::Csv, new Sheet('', ['a'], []), $stream);
        rewind($stream);

        self::assertSame("a\r\n", stream_get_contents($stream));
    }

    public function testItSaysWhenNoWriterWritesAFormat(): void
    {
        $writers = new ExportWriters([new CsvWriter()]);

        self::assertFalse($writers->has(Format::Xlsx));
        self::assertSame([Format::Csv], $writers->formats());
        $this->expectExceptionObject(ExportRefused::because('No writer writes the format "xlsx".'));

        $writers->get(Format::Xlsx);
    }

    public function testItRefusesTwoWritersOfOneFormat(): void
    {
        $this->expectExceptionObject(ExportRefused::because('Two writers write the format "csv".'));

        new ExportWriters([new CsvWriter(), new CsvWriter(';')]);
    }

    public function testAFormatIsNamedAndNamesItsFile(): void
    {
        self::assertSame(Format::Xlsx, Format::named('XLSX'));
        self::assertSame('text/csv; charset=UTF-8', Format::Csv->mediaType());
        self::assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', Format::Xlsx->mediaType());
        self::assertSame('books-2026_11.csv', Format::Csv->fileName('books 2026_11'));
        self::assertSame('a-b.xlsx', Format::Xlsx->fileName('/a/../b/'));
        self::assertSame('export.csv', Format::Csv->fileName('éé'));
        self::assertSame(str_repeat('a', 100).'.csv', Format::Csv->fileName(str_repeat('a', 120)));
        self::assertSame(str_repeat('a', 99).'.csv', Format::Csv->fileName(str_repeat('a', 99).' b'));

        $this->expectExceptionObject(ExportRefused::because('"pdf" is not a format: csv or xlsx.'));

        Format::named('pdf');
    }
}
