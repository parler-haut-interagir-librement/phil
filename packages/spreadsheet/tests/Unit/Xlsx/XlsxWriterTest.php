<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Xlsx;

use DateTimeImmutable;
use Dom\XMLDocument;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Column;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Headings;
use Phil\Spreadsheet\Internal\BufferedFile;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Internal\SheetRows;
use Phil\Spreadsheet\RowRange;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;
use Phil\Spreadsheet\Xlsx\CellReference;
use Phil\Spreadsheet\Xlsx\ExcelDate;
use Phil\Spreadsheet\Xlsx\XlsxText;
use Phil\Spreadsheet\Xlsx\XlsxWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(XlsxWriter::class)]
#[CoversClass(BufferedFile::class)]
#[CoversClass(Output::class)]
#[CoversClass(SheetRows::class)]
#[CoversClass(CellReference::class)]
#[CoversClass(ExcelDate::class)]
#[CoversClass(XlsxText::class)]
final class XlsxWriterTest extends TestCase
{
    use TemporaryFiles;

    private const string MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * Writes the workbook and gives the parts of the archive, by name, in their order.
     *
     * @return array<string, string>
     */
    private function parts(Workbook $workbook, WriteOptions $options = new WriteOptions()): array
    {
        $path = $this->path('book.xlsx');
        $stream = fopen($path, 'w');
        self::assertIsResource($stream);
        new XlsxWriter($this->folder())->write($workbook, $stream, $options);
        fclose($stream);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CHECKCONS));
        $parts = [];

        for ($index = 0; $index < $zip->count(); ++$index) {
            $name = (string) $zip->getNameIndex($index);
            $parts[$name] = (string) $zip->getFromIndex($index);
            self::assertSame('1980', date('Y', $zip->statIndex($index)['mtime'] ?? 0), $name.' dates from 1980.');
        }

        $zip->close();
        self::assertSame(['book.xlsx'], $this->files(), 'No temporary file is left.');

        return $parts;
    }

    private function xml(string $part): XMLDocument
    {
        return XMLDocument::createFromString($part);
    }

    public function testTheArchiveHoldsTheWellFormedPartsOfAWorkbook(): void
    {
        self::assertSame(Format::Xlsx, new XlsxWriter()->format());

        $parts = $this->parts(new Workbook(new Sheet('One & "two"', [['a']]), new Sheet('Second', [[1]])));

        self::assertSame(['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml', 'xl/worksheets/sheet2.xml'], array_keys($parts));

        foreach ($parts as $part) {
            self::assertStringStartsWith('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n", $part);
        }

        $types = $this->xml($parts['[Content_Types].xml']);
        $overrides = [];

        foreach ($types->getElementsByTagName('Override') as $override) {
            $overrides[(string) $override->getAttribute('PartName')] = (string) $override->getAttribute('ContentType');
        }

        self::assertSame([
            '/xl/workbook.xml' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
            '/xl/worksheets/sheet1.xml' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml',
            '/xl/worksheets/sheet2.xml' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml',
            '/xl/styles.xml' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml',
        ], $overrides);

        $workbook = $this->xml($parts['xl/workbook.xml']);
        self::assertSame(self::MAIN, $workbook->documentElement?->namespaceURI);
        $sheets = [];

        foreach ($workbook->getElementsByTagName('sheet') as $sheet) {
            $sheets[] = [$sheet->getAttribute('name'), $sheet->getAttribute('sheetId'), $sheet->getAttribute('r:id')];
        }

        self::assertSame([['One & "two"', '1', 'rId1'], ['Second', '2', 'rId2']], $sheets);

        $relationships = [];

        foreach ($this->xml($parts['xl/_rels/workbook.xml.rels'])->getElementsByTagName('Relationship') as $relationship) {
            $relationships[(string) $relationship->getAttribute('Id')] = [$relationship->getAttribute('Type'), $relationship->getAttribute('Target')];
        }

        $type = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';
        self::assertSame(['rId1' => [$type.'worksheet', 'worksheets/sheet1.xml'], 'rId2' => [$type.'worksheet', 'worksheets/sheet2.xml'], 'rId3' => [$type.'styles', 'styles.xml']], $relationships);
        self::assertStringContainsString('Type="'.$type.'officeDocument" Target="xl/workbook.xml"', $parts['_rels/.rels']);

        $styles = $this->xml($parts['xl/styles.xml']);
        self::assertSame(6, $styles->getElementsByTagName('cellXfs')->item(0)?->getElementsByTagName('xf')->length);
        self::assertStringContainsString('<numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm:ss"/>', $parts['xl/styles.xml']);
        self::assertStringContainsString('<font><b/><sz val="11"/></font>', $parts['xl/styles.xml']);
    }

    public function testEachCellIsWrittenWithItsType(): void
    {
        $row = ['00123', 12, 1.5, true, false, Cell::date(new DateTimeImmutable('2026-11-21')), new DateTimeImmutable('2026-11-21 12:00'), null, ' padded ', '<&>'];
        $sheet = $this->parts(Workbook::single('Data', [$row]))['xl/worksheets/sheet1.xml'];

        self::assertSame(self::MAIN, $this->xml($sheet)->documentElement?->namespaceURI);
        self::assertStringContainsString('<sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetData><row r="1">'
            .'<c r="A1" t="inlineStr"><is><t>00123</t></is></c>'
            .'<c r="B1"><v>12</v></c>'
            .'<c r="C1"><v>1.5</v></c>'
            .'<c r="D1" t="b"><v>1</v></c>'
            .'<c r="E1" t="b"><v>0</v></c>'
            .'<c r="F1" s="1"><v>46347</v></c>'
            .'<c r="G1" s="2"><v>46347.5</v></c>'
            .'<c r="I1" t="inlineStr"><is><t xml:space="preserve"> padded </t></is></c>'
            .'<c r="J1" t="inlineStr"><is><t>&lt;&amp;&gt;</t></is></c>'
            .'</row></sheetData></worksheet>', $sheet);
    }

    public function testTheHeadingsAreBoldAndFrozenAndTheWidthsSet(): void
    {
        $columns = [new Column('a', 'Name', 20), new Column('b'), new Column('=c', width: 8.5)];
        $sheet = $this->parts(Workbook::single('Data', [['x', '=1+1']], $columns))['xl/worksheets/sheet1.xml'];

        self::assertStringContainsString('<sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView>', $sheet);
        self::assertStringContainsString('<cols><col min="1" max="1" width="20" customWidth="1"/><col min="3" max="3" width="8.5" customWidth="1"/></cols>', $sheet);
        self::assertStringContainsString('<row r="1"><c r="A1" s="4" t="inlineStr"><is><t>Name</t></is></c><c r="B1" s="4" t="inlineStr"><is><t>b</t></is></c><c r="C1" s="5" t="inlineStr"><is><t>=c</t></is></c></row>', $sheet);
        self::assertStringContainsString('<row r="2"><c r="A2" t="inlineStr"><is><t>x</t></is></c><c r="B2" s="3" t="inlineStr"><is><t>=1+1</t></is></c></row>', $sheet);
    }

    public function testTheGuardCanBeTurnedOffAndTheHeadingsLeftOut(): void
    {
        $sheet = $this->parts(Workbook::single('Data', [['=1+1']], [new Column('=a')]), new WriteOptions(Headings::None, guardFormulas: false))['xl/worksheets/sheet1.xml'];

        self::assertStringContainsString('<sheetView workbookViewId="0"/>', $sheet);
        self::assertStringContainsString('<sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>=1+1</t></is></c></row></sheetData>', $sheet);

        $codes = $this->parts(Workbook::single('Data', [], [new Column('=a', 'Label')]), new WriteOptions(Headings::Codes, guardFormulas: false))['xl/worksheets/sheet1.xml'];
        self::assertStringContainsString('<c r="A1" s="4" t="inlineStr"><is><t>=a</t></is></c>', $codes);
    }

    public function testARangeWritesItsRows(): void
    {
        $sheet = $this->parts(Workbook::single('Data', [[1], [2], [3]]), new WriteOptions(range: new RowRange(2, 2)))['xl/worksheets/sheet1.xml'];

        self::assertStringContainsString('<sheetData><row r="1"><c r="A1"><v>2</v></c></row></sheetData>', $sheet);
    }

    public function testManyRowsAreWrittenInChunks(): void
    {
        $rows = (static function (): iterable {
            for ($i = 1; $i <= 3000; ++$i) {
                yield [str_repeat('x', 30), $i];
            }
        })();
        $sheet = $this->parts(Workbook::single('Data', $rows))['xl/worksheets/sheet1.xml'];

        self::assertSame(3000, substr_count($sheet, '<row '));
        self::assertStringEndsWith('<c r="B3000"><v>3000</v></c></row></sheetData></worksheet>', $sheet);
        self::assertInstanceOf(XMLDocument::class, $this->xml($sheet));
    }

    public function testTheColumnLettersGoPastZ(): void
    {
        $sheet = $this->parts(Workbook::single('Data', [array_fill(0, 28, 1)]))['xl/worksheets/sheet1.xml'];

        self::assertStringContainsString('<c r="Z1"><v>1</v></c><c r="AA1"><v>1</v></c><c r="AB1"><v>1</v></c></row>', $sheet);
    }

    public function testAWorkbookWithTooManyColumnsIsRefusedAndLeavesNoFile(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        self::assertCount(16_384, $this->xlsxColumns(16_384));

        try {
            new XlsxWriter($this->folder())->write(Workbook::single('Data', [$this->xlsxColumns(16_385)]), $stream);
            self::fail('Too many columns.');
        } catch (WriteFailed $writeFailed) {
            self::assertSame('An XLSX sheet holds at most 16384 columns.', $writeFailed->getMessage());
        }

        self::assertSame([], glob($this->folder().'/*'));
        self::assertSame(0, fstat($stream)['size'] ?? null);
    }

    public function testAColumnLimitAtTheEdgeIsWritten(): void
    {
        $sheet = $this->parts(Workbook::single('Data', [$this->xlsxColumns(16_384)]))['xl/worksheets/sheet1.xml'];

        self::assertStringContainsString('<c r="XFD1"><v>1</v></c></row>', $sheet);
    }

    /**
     * @return list<int>
     */
    private function xlsxColumns(int $count): array
    {
        return array_fill(0, $count, 1);
    }

    public function testATextLongerThanACellHoldsIsRefused(): void
    {
        self::assertStringContainsString(str_repeat('a', 32767), $this->parts(Workbook::single('Data', [[str_repeat('a', 32767)]]))['xl/worksheets/sheet1.xml']);

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('The cell B1 holds more than the 32767 characters of an XLSX text.');

        $this->parts(Workbook::single('Data', [[null, str_repeat('a', 32768)]]));
    }

    public function testADateBeforeMarch1900IsRefused(): void
    {
        $this->expectException(WriteFailed::class);

        $this->parts(Workbook::single('Data', [[new DateTimeImmutable('1899-01-01')]]));
    }

    public function testTheRowLimitIsHeld(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        new XlsxWriter($this->folder(), 3)->write(Workbook::single('Data', [[1], [2]], [new Column('a')]), $stream);

        self::assertGreaterThan(0, fstat($stream)['size'] ?? 0);

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('An XLSX sheet holds at most 3 rows, headings included.');

        new XlsxWriter($this->folder(), 3)->write(Workbook::single('Data', [[1], [2], [3]], [new Column('a')]), $stream);
    }

    public function testTheRowLimitLiesWithinTheFormat(): void
    {
        self::assertInstanceOf(XlsxWriter::class, new XlsxWriter(null, 1));
        self::assertInstanceOf(XlsxWriter::class, new XlsxWriter(null, 1_048_576));

        foreach ([0, 1_048_577] as $limit) {
            try {
                new XlsxWriter(null, $limit);
                self::fail('The limit is refused.');
            } catch (InvalidSpreadsheet $invalidSpreadsheet) {
                self::assertSame('The most rows of a sheet is between 1 and 1048576.', $invalidSpreadsheet->getMessage());
            }
        }
    }

    public function testAMissingTemporaryDirectoryIsRefused(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('No temporary file can be made in "'.$this->folder().'/missing".');

        new XlsxWriter($this->folder().'/missing')->write(Workbook::single('Data', []), $stream);
    }

    public function testAStreamThatRefusesTheArchiveFails(): void
    {
        $stream = fopen('php://memory', 'r');
        self::assertIsResource($stream);

        $this->expectException(WriteFailed::class);
        $this->expectExceptionMessageIsOrContains('The stream refused the file.');

        new XlsxWriter($this->folder())->write(Workbook::single('Data', [['a']]), $stream);
    }

    public function testItWritesToAnOpenStreamOnly(): void
    {
        $this->expectException(InvalidSpreadsheet::class);

        new XlsxWriter()->write(Workbook::single('a', []), stream_context_create());
    }

    public function testTheSystemTemporaryDirectoryIsTheDefault(): void
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        new XlsxWriter()->write(Workbook::single('a', [['x']]), $stream);
        rewind($stream);

        self::assertSame("PK\x03\x04", fread($stream, 4));
    }
}
