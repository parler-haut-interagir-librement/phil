<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Tests\Unit\Xlsx;

use DateTimeImmutable;
use DOMDocument;
use Generator;
use Override;
use Phil\DataTableExport\Exception\ExportRefused;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;
use Phil\DataTableExport\Xlsx\SheetXml;
use Phil\DataTableExport\Xlsx\XlsxWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(XlsxWriter::class)]
#[CoversClass(SheetXml::class)]
final class XlsxWriterTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-data-table-export-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testItWritesAWorkbookOfOneWorksheet(): void
    {
        $parts = $this->parts($this->write(new Sheet('Books: [2026]', ['Title', 'Pages', 'Read', 'On'], [
            ['Dune', 412, true, new DateTimeImmutable('2026-11-12 00:00 UTC')],
            ['=HYPERLINK("x")', 3.5, null, ''],
        ])));

        self::assertSame(['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'], array_keys($parts));

        foreach ($parts as $name => $xml) {
            self::assertNotSame('', $xml);
            self::assertTrue(new DOMDocument()->loadXML($xml), $name.' is well-formed');
        }

        self::assertStringContainsString('<sheet name="Books   2026" sheetId="1" r:id="rId1"/>', $parts['xl/workbook.xml']);
        self::assertStringContainsString('<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>', $parts['_rels/.rels']);
        self::assertStringContainsString('Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>', $parts['xl/_rels/workbook.xml.rels']);
        self::assertStringContainsString('<Override PartName="/xl/worksheets/sheet1.xml"', $parts['[Content_Types].xml']);
        self::assertStringContainsString('<cellXfs count="4">', $parts['xl/styles.xml']);
        self::assertSame(
            '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>'
            .'<row r="1"><c r="A1" t="inlineStr" s="1"><is><t xml:space="preserve">Title</t></is></c><c r="B1" t="inlineStr" s="1"><is><t xml:space="preserve">Pages</t></is></c><c r="C1" t="inlineStr" s="1"><is><t xml:space="preserve">Read</t></is></c><c r="D1" t="inlineStr" s="1"><is><t xml:space="preserve">On</t></is></c></row>'
            .'<row r="2"><c r="A2" t="inlineStr"><is><t xml:space="preserve">Dune</t></is></c><c r="B2"><v>412</v></c><c r="C2" t="b"><v>1</v></c><c r="D2" s="2"><v>46338</v></c></row>'
            .'<row r="3"><c r="A3" t="inlineStr"><is><t xml:space="preserve">=HYPERLINK(&quot;x&quot;)</t></is></c><c r="B3"><v>3.5</v></c></row>'
            .'</sheetData></worksheet>',
            substr($parts['xl/worksheets/sheet1.xml'], (int) strpos($parts['xl/worksheets/sheet1.xml'], '<sheetViews>')),
        );
        self::assertSame([], glob($this->directory.'/*'), 'the temporary files are removed');
    }

    public function testWithoutHeadingsNothingIsFrozen(): void
    {
        $sheet = $this->parts($this->write(new Sheet('', [], [['a']])))['xl/worksheets/sheet1.xml'];

        self::assertStringContainsString('<sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetData><row r="1"><c r="A1"', $sheet);
        self::assertStringContainsString('<sheet name="Sheet1"', $this->parts($this->write(new Sheet("''", [], [])))['xl/workbook.xml']);
    }

    public function testTheSameSheetGivesTheSameBytes(): void
    {
        $sheet = static fn (): Sheet => new Sheet('A', ['x'], [[1]]);

        self::assertSame($this->write($sheet()), $this->write($sheet()));
        self::assertSame(Format::Xlsx, new XlsxWriter()->format());
    }

    public function testManyRowsAreWrittenInChunks(): void
    {
        $rows = (static function (): Generator {
            for ($row = 1; $row <= 3000; ++$row) {
                yield [$row, str_repeat('x', 40)];
            }
        })();
        $sheet = $this->parts($this->write(new Sheet('Many', ['n', 'text'], $rows)))['xl/worksheets/sheet1.xml'];

        self::assertSame(3001, substr_count($sheet, '<row '));
        self::assertStringContainsString('<row r="3001"><c r="A3001"><v>3000</v></c>', $sheet);
        self::assertStringEndsWith('</row></sheetData></worksheet>', $sheet);
    }

    public function testTheFixedPartsOfTheWorkbookAreExactlyThese(): void
    {
        $parts = $this->parts($this->write(new Sheet('Books', ['T'], [['a']])));
        $worksheet = (string) array_pop($parts);

        self::assertSame([
            '[Content_Types].xml' => "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<Types xmlns=\"http://schemas.openxmlformats.org/package/2006/content-types\"><Default Extension=\"rels\" ContentType=\"application/vnd.openxmlformats-package.relationships+xml\"/><Default Extension=\"xml\" ContentType=\"application/xml\"/><Override PartName=\"/xl/workbook.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml\"/><Override PartName=\"/xl/worksheets/sheet1.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/><Override PartName=\"/xl/styles.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml\"/></Types>",
            '_rels/.rels' => "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument\" Target=\"xl/workbook.xml\"/></Relationships>",
            'xl/workbook.xml' => "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<workbook xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><sheets><sheet name=\"Books\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>",
            'xl/_rels/workbook.xml.rels' => "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<Relationships xmlns=\"http://schemas.openxmlformats.org/package/2006/relationships\"><Relationship Id=\"rId1\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet1.xml\"/><Relationship Id=\"rId2\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/></Relationships>",
            'xl/styles.xml' => "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<styleSheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\"><numFmts count=\"2\"><numFmt numFmtId=\"164\" formatCode=\"yyyy-mm-dd\"/><numFmt numFmtId=\"165\" formatCode=\"yyyy-mm-dd hh:mm:ss\"/></numFmts><fonts count=\"2\"><font><sz val=\"11\"/><name val=\"Calibri\"/></font><font><b/><sz val=\"11\"/><name val=\"Calibri\"/></font></fonts><fills count=\"2\"><fill><patternFill patternType=\"none\"/></fill><fill><patternFill patternType=\"gray125\"/></fill></fills><borders count=\"1\"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count=\"1\"><xf numFmtId=\"0\" fontId=\"0\" fillId=\"0\" borderId=\"0\"/></cellStyleXfs><cellXfs count=\"4\"><xf numFmtId=\"0\" fontId=\"0\" fillId=\"0\" borderId=\"0\" xfId=\"0\"/><xf numFmtId=\"0\" fontId=\"1\" fillId=\"0\" borderId=\"0\" xfId=\"0\" applyFont=\"1\"/><xf numFmtId=\"164\" fontId=\"0\" fillId=\"0\" borderId=\"0\" xfId=\"0\" applyNumberFormat=\"1\"/><xf numFmtId=\"165\" fontId=\"0\" fillId=\"0\" borderId=\"0\" xfId=\"0\" applyNumberFormat=\"1\"/></cellXfs><cellStyles count=\"1\"><cellStyle name=\"Normal\" xfId=\"0\" builtinId=\"0\"/></cellStyles></styleSheet>",
        ], $parts);
        self::assertSame(
            "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"yes\"?>\n<worksheet xmlns=\"http://schemas.openxmlformats.org/spreadsheetml/2006/main\" xmlns:r=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships\"><sheetViews>",
            substr($worksheet, 0, (int) strpos($worksheet, '<sheetViews>') + 12),
        );
    }

    public function testTheTemporaryFilesAreMadeInTheDirectoryGiven(): void
    {
        $seen = [];
        $rows = (function () use (&$seen): Generator {
            $seen = $this->temporaries();

            yield ['a'];
        })();

        $this->write(new Sheet('', [], $rows));

        self::assertCount(2, $seen);
    }

    public function testTheRowsAreWrittenOnceAChunkIsFull(): void
    {
        $head = \strlen('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetData>');
        $frame = $head + \strlen('<row r="1">'.SheetXml::cell('A1', str_repeat('x', 20000)).SheetXml::cell('B1', str_repeat('x', 20000)).SheetXml::cell('C1', 'x').'</row>') - 1;
        $written = function (): int {
            clearstatcache();

            return max([0, ...array_map(static function (string $file): int {
                $size = filesize($file);

                return false === $size ? 0 : $size;
            }, $this->temporaries())]);
        };

        foreach ([[65536 - $frame, 65536], [65535 - $frame, 0], [10, 0]] as [$length, $flushed]) {
            $size = null;
            $rows = (static function () use ($length, $written, &$size): Generator {
                yield [str_repeat('x', 20000), str_repeat('x', 20000), str_repeat('x', $length)];

                $size = $written();

                yield ['y'];
            })();

            $this->write(new Sheet('', [], $rows));

            self::assertSame($flushed, $size, 'a chunk is written when it reaches 65,536 bytes');
        }
    }

    public function testTheTitleOfAWorksheetIsTrimmedBeforeAndAfterItIsCut(): void
    {
        self::assertSame(str_repeat('a', 31), XlsxWriter::worksheetName("'".str_repeat('a', 40)));
        self::assertSame(str_repeat('a', 30), XlsxWriter::worksheetName(str_repeat('a', 30).' bbb'));
        self::assertSame('a', XlsxWriter::worksheetName("' a '"));
    }

    public function testAWorksheetNameKeepsToWhatASpreadsheetAllows(): void
    {
        self::assertSame('a b c d e f g', XlsxWriter::worksheetName('a[b]c:d*e?f/g'));
        self::assertSame('back slash', XlsxWriter::worksheetName('back\\slash'));
        self::assertSame('quoted', XlsxWriter::worksheetName(" 'quoted' "));
        self::assertSame(str_repeat('é', 31), XlsxWriter::worksheetName(str_repeat('é', 40)));
        self::assertSame(str_repeat('a', 30), XlsxWriter::worksheetName(str_repeat('a', 30)." '"));
        self::assertSame('Sheet1', XlsxWriter::worksheetName("\x01"));
        self::assertSame('Sheet1', XlsxWriter::worksheetName(''));
    }

    public function testItRefusesMoreColumnsThanASpreadsheetHolds(): void
    {
        $this->expectExceptionObject(ExportRefused::because('A sheet holds at most 16384 columns.'));

        $this->write(new Sheet('', [], [array_fill(0, XlsxWriter::COLUMN_LIMIT + 1, 1)]));
    }

    public function testItTakesAsManyColumnsAsASpreadsheetHolds(): void
    {
        $sheet = $this->parts($this->write(new Sheet('', [], [array_fill(0, XlsxWriter::COLUMN_LIMIT, 1)])))['xl/worksheets/sheet1.xml'];

        self::assertStringContainsString('<c r="XFD1"><v>1</v></c></row>', $sheet);
    }

    public function testItRefusesMoreRowsThanItsLimitAndRemovesItsFiles(): void
    {
        $stream = fopen('php://memory', 'w');
        self::assertIsResource($stream);
        new XlsxWriter($this->directory, 3)->write(new Sheet('', ['h'], [[1], [2]]), $stream);

        try {
            new XlsxWriter($this->directory, 3)->write(new Sheet('', ['h'], [[1], [2], [3]]), $stream);
            self::fail('The rows past the limit are refused.');
        } catch (ExportRefused $exportRefused) {
            self::assertSame('A sheet holds at most 3 rows, headings included.', $exportRefused->getMessage());
        }

        self::assertSame([], glob($this->directory.'/*'));
    }

    public function testItRefusesALimitPastTheSpreadsheets(): void
    {
        new XlsxWriter(rowLimit: 1);
        new XlsxWriter(rowLimit: XlsxWriter::ROW_LIMIT);

        foreach ([0, XlsxWriter::ROW_LIMIT + 1] as $limit) {
            try {
                new XlsxWriter(rowLimit: $limit);
                self::fail(\sprintf('The limit %d is refused.', $limit));
            } catch (ExportRefused $refused) {
                self::assertSame('The most rows of a sheet is between 1 and 1048576.', $refused->getMessage());
            }
        }
    }

    public function testItSaysWhenTheStreamRefusesTheWorkbook(): void
    {
        $stream = fopen(__FILE__, 'r');
        self::assertIsResource($stream);

        try {
            new XlsxWriter($this->directory)->write(new Sheet('', [], []), $stream);
            self::fail('A stream that refuses the workbook is said.');
        } catch (ExportRefused $exportRefused) {
            self::assertSame('The stream refused the workbook.', $exportRefused->getMessage());
        } finally {
            fclose($stream);
        }

        self::assertSame([], glob($this->directory.'/*'));
    }

    /**
     * @return list<string> the temporary files of the writer, in the directory of the test
     */
    private function temporaries(): array
    {
        $files = glob($this->directory.'/phil-xlsx-*');

        return false === $files ? [] : $files;
    }

    private function write(Sheet $sheet): string
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        new XlsxWriter($this->directory)->write($sheet, $stream);
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    /**
     * @return array<string, string> the parts of the workbook, by name, in the order of the archive
     */
    private function parts(string $bytes): array
    {
        $path = $this->directory.'/read.xlsx';
        file_put_contents($path, $bytes);
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::RDONLY));
        $parts = [];

        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $name = (string) $zip->getNameIndex($index);
            $parts[$name] = (string) $zip->getFromIndex($index);
            self::assertLessThan(strtotime('2000-01-01'), $zip->statIndex($index)['mtime'] ?? \PHP_INT_MAX, 'every entry dates from 1980');
        }

        $zip->close();
        unlink($path);

        return $parts;
    }
}
