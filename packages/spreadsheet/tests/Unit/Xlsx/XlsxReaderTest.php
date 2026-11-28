<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Xlsx;

use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\Archive;
use Phil\Spreadsheet\Internal\BudgetFilter;
use Phil\Spreadsheet\Internal\ByteBudget;
use Phil\Spreadsheet\Internal\RowBuilder;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Tests\Support\Workbooks;
use Phil\Spreadsheet\Xlsx\CellReference;
use Phil\Spreadsheet\Xlsx\ExcelDate;
use Phil\Spreadsheet\Xlsx\NumberFormats;
use Phil\Spreadsheet\Xlsx\XlsxReader;
use Phil\Spreadsheet\Xlsx\XlsxSheetParser;
use Phil\Spreadsheet\Xlsx\XlsxText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

#[CoversClass(XlsxReader::class)]
#[CoversClass(XlsxSheetParser::class)]
#[CoversClass(Archive::class)]
#[CoversClass(SafeXmlReader::class)]
#[CoversClass(BudgetFilter::class)]
#[CoversClass(ByteBudget::class)]
#[CoversClass(RowBuilder::class)]
#[CoversClass(CellReference::class)]
#[CoversClass(ExcelDate::class)]
#[CoversClass(NumberFormats::class)]
#[CoversClass(XlsxText::class)]
#[CoversClass(Values::class)]
final class XlsxReaderTest extends TestCase
{
    use TemporaryFiles;

    /**
     * @param array<string, string> $extra
     *
     * @return array<int, list<string>>
     */
    private function read(string $sheetData, array $extra = [], ?string $sheet = null, ReadLimits $limits = new ReadLimits(), string $workbookPr = ''): array
    {
        return Workbooks::dump(new XlsxReader($limits)->rows($this->zip('book.xlsx', Workbooks::xlsx($sheetData, $extra, $workbookPr)), $sheet));
    }

    public function testItReadsTheSheetsInTheirOrder(): void
    {
        $path = $this->zip('book.xlsx', Workbooks::xlsx(''));

        self::assertSame(Format::Xlsx, new XlsxReader()->format());
        self::assertSame(['First', 'Second'], new XlsxReader()->sheetNames($path));
        self::assertSame([1 => ['text:second']], Workbooks::dump(new XlsxReader()->rows($path, 'Second')));
    }

    public function testEachCellIsReadWithItsType(): void
    {
        $rows = $this->read(
            '<row r="1">'
            .'<c r="A1" t="s"><v>0</v></c>'
            .'<c r="B1" t="s"><v>1</v></c>'
            .'<c r="C1" t="inlineStr"><is><r><t>in</t></r><r><t xml:space="preserve">line </t></r><rPh><t>no</t></rPh></is></c>'
            .'<c r="D1" t="str"><f>A1</f><v>formula _x0041_</v></c>'
            .'<c r="E1" t="e"><v>#DIV/0!</v></c>'
            .'<c r="F1" t="b"><v>1</v></c>'
            .'<c r="G1" t="b"><v>0</v></c>'
            .'<c r="H1" t="b"><v>true</v></c>'
            .'<c r="I1"><v>12</v></c>'
            .'<c r="J1" t="n"><v> -1.5E2 </v></c>'
            .'<c r="K1" t="d"><v>2026-11-21T14:30:00</v></c>'
            .'<c r="L1" t="d"><v>2026-11-21</v></c>'
            .'<c r="M1" s="1"><v>46347</v></c>'
            .'<c r="N1" s="2"><v>46347.5</v></c>'
            .'<c r="O1" s="3"><v>46347</v></c>'
            .'<c r="P1" s="4"><v>46347</v></c>'
            .'<c r="Q1" s="1"><v>-3</v></c>'
            .'<c r="R1" s="9"><v>7</v></c>'
            .'</row>',
        );

        self::assertSame([1 => [
            'text:zero',
            'text:one rich',
            'text:inline ',
            'text:formula A',
            'text:#DIV/0!',
            'boolean:true',
            'boolean:false',
            'boolean:true',
            'number:12',
            'number:-150.0',
            'date-time:2026-11-21T14:30:00.000+00:00',
            'date:2026-11-21T00:00:00.000+00:00',
            'date:2026-11-21T00:00:00.000+00:00',
            'date-time:2026-11-21T12:00:00.000+00:00',
            'date:2026-11-21T00:00:00.000+00:00',
            'number:46347',
            'number:-3',
            'number:7',
        ]], $rows);
    }

    public function testEmptyCellsFillTheGapsAndTheEndIsTrimmed(): void
    {
        $rows = $this->read(
            '<row r="2"><c r="C2"><v>1</v></c><c r="E2" s="1"/><c r="F2"><v></v></c><c r="G2" t="b"><v/></c><c r="H2" t="d"/><c t="inlineStr"><is><t></t></is></c><c t="s"><v>2</v></c></row>'
            .'<row r="4"/>'
            .'<row><c><v>1</v></c><c><v>2</v></c><c r="E"><v>3</v></c><c><v>4</v></c></row>'
            .'<row r="9" spans="1:2"><c r="A9" t="d"><v></v></c></row>',
        );

        self::assertSame([
            2 => ['empty:', 'empty:', 'number:1'],
            5 => ['number:1', 'number:2', 'empty:', 'empty:', 'number:3', 'number:4'],
        ], $rows);
    }

    public function testThe1904DateSystemIsRead(): void
    {
        self::assertSame([1 => ['date:1904-01-02T00:00:00.000+00:00']], $this->read('<row r="1"><c r="A1" s="1"><v>1</v></c></row>', workbookPr: '<workbookPr date1904="1"/>'));
        self::assertSame([1 => ['date:1904-01-02T00:00:00.000+00:00']], $this->read('<row r="1"><c r="A1" s="1"><v>1</v></c></row>', workbookPr: '<workbookPr date1904="true"/>'));
        self::assertSame([1 => ['date:1900-01-01T00:00:00.000+00:00']], $this->read('<row r="1"><c r="A1" s="1"><v>1</v></c></row>', workbookPr: '<workbookPr date1904="false"/>'));
    }

    public function testAWorkbookWithoutSharedTextsStylesOrRootRelationshipsIsRead(): void
    {
        $entries = Workbooks::xlsx('<row r="1"><c r="A1" s="1"><v>5</v></c></row>');
        unset($entries['_rels/.rels'], $entries['xl/sharedStrings.xml'], $entries['xl/styles.xml']);
        $entries['xl/_rels/workbook.xml.rels'] = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="worksheet" Target="worksheets/sheet2.xml"/></Relationships>';

        self::assertSame([1 => ['number:5']], Workbooks::dump(new XlsxReader()->rows($this->zip('bare.xlsx', $entries))));
    }

    public function testAWorkbookAtTheRootIsRead(): void
    {
        $entries = Workbooks::xlsx('');
        $entries['_rels/.rels'] = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId9" Type="'.Workbooks::RELATIONSHIPS.'/metadata" Target="docProps/core.xml"/><Relationship Id="rId1" Type="'.Workbooks::RELATIONSHIPS.'/officeDocument" Target="/book.xml"/><Relationship Id="rId2" Type="'.Workbooks::RELATIONSHIPS.'/officeDocument" Target="other.xml"/></Relationships>';
        $entries['book.xml'] = '<workbook xmlns="'.Workbooks::MAIN.'" xmlns:r="'.Workbooks::RELATIONSHIPS.'"><sheets><sheet name="Root" r:id="rId1"/><sheet name="Chart" r:id="rId2"/></sheets></workbook>';
        $entries['_rels/book.xml.rels'] = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="'.Workbooks::RELATIONSHIPS.'/worksheet" Target="./xl/worksheets/sheet1.xml"/><Relationship Id="rId2" Type="'.Workbooks::RELATIONSHIPS.'/chartsheet" Target="xl/chart.xml"/></Relationships>';
        $entries['xl/worksheets/sheet1.xml'] = '<worksheet xmlns="'.Workbooks::MAIN.'"><sheetData><row><c t="inlineStr"><is><t>root</t></is></c></row></sheetData></worksheet>';
        $path = $this->zip('root.xlsx', $entries);

        self::assertSame(['Root'], new XlsxReader()->sheetNames($path));
        self::assertSame([1 => ['text:root']], Workbooks::dump(new XlsxReader()->rows($path)));
    }

    public function testAMissingSheetIsRefused(): void
    {
        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The workbook has no sheet "Third".');

        $this->read('', sheet: 'Third');
    }

    public function testAWorkbookWithoutSheetsIsRefused(): void
    {
        $entries = Workbooks::xlsx('');
        $entries['xl/workbook.xml'] = '<workbook xmlns="'.Workbooks::MAIN.'"><sheets/></workbook>';

        $this->expectExceptionMessageIsOrContains('The workbook has no sheet "".');

        iterator_to_array(new XlsxReader()->rows($this->zip('empty.xlsx', $entries)));
    }

    public function testASheetWithoutItsPartIsRefused(): void
    {
        $entries = Workbooks::xlsx('');
        $entries['xl/workbook.xml'] = '<workbook xmlns="'.Workbooks::MAIN.'" xmlns:r="'.Workbooks::RELATIONSHIPS.'"><sheets><sheet name="Lost" r:id="rId8"/></sheets></workbook>';

        $this->expectExceptionMessageIsOrContains('The part "xl/workbook.xml" names the sheet "Lost" without its part.');

        new XlsxReader()->sheetNames($this->zip('lost.xlsx', $entries));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function malformedSheets(): iterable
    {
        yield 'cell outside a row' => ['<c r="A1"><v>1</v></c>', 'has a cell outside a row'];
        yield 'row number' => ['<row r="0"><c><v>1</v></c></row>', 'has a row without a number'];
        yield 'row letters' => ['<row r="x"><c><v>1</v></c></row>', 'has a row without a number'];
        yield 'reference' => ['<row r="1"><c r="11"><v>1</v></c></row>', 'has a cell without a reference'];
        yield 'shared index' => ['<row r="1"><c t="s"><v>3</v></c></row>', 'refers to a shared text it does not have'];
        yield 'shared text' => ['<row r="1"><c t="s"><v>x</v></c></row>', 'refers to a shared text it does not have'];
        yield 'shared none' => ['<row r="1"><c t="s"><f>x</f></c></row>', 'refers to a shared text it does not have'];
        yield 'date' => ['<row r="1"><c t="d"><v>21/11/2026</v></c></row>', 'has a date cell that holds no date'];
        yield 'number' => ['<row r="1"><c><v>12abc</v></c></row>', 'has a number cell that holds no number'];
        yield 'type' => ['<row r="1"><c t="x"><v>1</v></c></row>', 'has a cell of an unknown type'];
    }

    #[DataProvider('malformedSheets')]
    public function testAMalformedSheetIsRefused(string $sheetData, string $reason): void
    {
        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The part "xl/worksheets/sheet1.xml" '.$reason.'.');

        $this->read($sheetData);
    }

    public function testATruncatedPartIsRefused(): void
    {
        $entries = Workbooks::xlsx('');
        $entries['xl/worksheets/sheet1.xml'] = '<worksheet xmlns="'.Workbooks::MAIN.'"><sheetData><row><c><v>1</v></c>';

        $this->expectExceptionMessageIsOrContains('The part "xl/worksheets/sheet1.xml" is not well formed.');

        iterator_to_array(new XlsxReader()->rows($this->zip('cut.xlsx', $entries)));
    }

    public function testADocumentTypeIsRefusedBeforeAnyEntityIsExpanded(): void
    {
        $secret = $this->file('secret.txt', 'secret');
        $entries = Workbooks::xlsx('');
        $entries['xl/worksheets/sheet1.xml'] = '<?xml version="1.0"?><!DOCTYPE worksheet [<!ENTITY x SYSTEM "file://'.$secret.'">]><worksheet xmlns="'.Workbooks::MAIN.'"><sheetData><row><c t="inlineStr"><is><t>&x;</t></is></c></row></sheetData></worksheet>';

        $this->expectExceptionMessageIsOrContains('The part "xl/worksheets/sheet1.xml" declares a document type, which a spreadsheet never needs.');

        iterator_to_array(new XlsxReader()->rows($this->zip('xxe.xlsx', $entries)));
    }

    public function testTheLimitsOfColumnsRowsAndCellsHold(): void
    {
        self::assertCount(1, $this->read('<row r="1"><c r="B1"><v>1</v></c></row>', limits: new ReadLimits(maxColumns: 2)));
        self::assertCount(1, $this->read('<row r="1"><c r="Z1" s="1"/><c r="A1"><v>1</v></c></row>', limits: new ReadLimits(maxColumns: 26)));

        try {
            $this->read('<row r="1"><c r="C1"/></row>', limits: new ReadLimits(maxColumns: 2));
            self::fail('The third column is past the limit.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The column of a cell goes over the limit of 2.', $readRefused->getMessage());
        }

        try {
            $this->read('<row r="1"><c><v>1</v></c><c><v>1</v></c><c><v>1</v></c></row>', limits: new ReadLimits(maxColumns: 2));
            self::fail('The third column is past the limit.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The column of a cell goes over the limit of 2.', $readRefused->getMessage());
        }

        self::assertCount(1, $this->read('<row r="3"><c><v>1</v></c></row><row r="9"/>', limits: new ReadLimits(maxRows: 3)));

        $this->expectExceptionMessageIsOrContains('The number of the row 4 goes over the limit of 3.');

        $this->read('<row r="4"><c><v>1</v></c></row>', limits: new ReadLimits(maxRows: 3));
    }

    public function testTheSharedTextsAreBoundedInMemory(): void
    {
        // Three texts of 4, 8 and 0 bytes, and 32 bytes each besides.
        self::assertCount(1, $this->read('<row><c t="s"><v>0</v></c></row>', limits: new ReadLimits(maxSharedStringsBytes: 4 + 8 + 96)));

        $this->expectExceptionMessageIsOrContains('The memory of the shared texts goes over the limit of 107.');

        $this->read('<row><c t="s"><v>0</v></c></row>', limits: new ReadLimits(maxSharedStringsBytes: 107));
    }

    public function testAPartOverItsSizeIsRefusedEvenWhenTheArchiveLies(): void
    {
        $sheet = '<row r="1">'.str_repeat('<c t="inlineStr"><is><t>long text</t></is></c>', 300).'</row>';
        $size = \strlen(Workbooks::xlsx($sheet)['xl/worksheets/sheet1.xml']);

        self::assertCount(1, $this->read($sheet, limits: new ReadLimits(maxPartBytes: $size)));

        try {
            $this->read($sheet, limits: new ReadLimits(maxPartBytes: $size - 1));
            self::fail('The part is too large.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The size of the part "xl/worksheets/sheet1.xml" goes over the limit of '.($size - 1).'.', $readRefused->getMessage());
        }

        // The budget alone, as when the size an archive declares is false.
        $reader = SafeXmlReader::fromString(Workbooks::xlsx($sheet)['xl/worksheets/sheet1.xml'], 'lying.xml', 8192);

        $this->expectExceptionMessageIsOrContains('The size of the part "lying.xml" goes over the limit of 8192.');

        while ($reader->read()) {
        }
    }

    public function testAnArchiveIsRefusedWhenItIsNoneOrGoesOverItsLimits(): void
    {
        try {
            new XlsxReader()->sheetNames($this->file('plain.xlsx', 'not a zip'));
            self::fail('Not an archive.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The file is not a ZIP archive.', $readRefused->getMessage());
        }

        try {
            new XlsxReader()->sheetNames($this->path('missing.xlsx'));
            self::fail('No file.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The file cannot be read.', $readRefused->getMessage());
        }

        $path = $this->zip('book.xlsx', Workbooks::xlsx(''));
        self::assertCount(2, new XlsxReader(new ReadLimits(maxFileBytes: (int) filesize($path), maxEntries: 7))->sheetNames($path));

        try {
            new XlsxReader(new ReadLimits(maxFileBytes: (int) filesize($path) - 1))->sheetNames($path);
            self::fail('The file is too large.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The size of the file goes over the limit of '.((int) filesize($path) - 1).'.', $readRefused->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The number of entries of the archive goes over the limit of 6.');

        new XlsxReader(new ReadLimits(maxEntries: 6))->sheetNames($path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function entriesOutsideTheRoot(): iterable
    {
        yield 'parent' => ['../evil.xml'];
        yield 'nested parent' => ['xl/../../evil.xml'];
        yield 'absolute' => ['/etc/evil.xml'];
        yield 'backslash' => ['xl\evil.xml'];
        yield 'drive' => ['C:evil.xml'];
        yield 'only parent' => ['xl/..'];
    }

    #[DataProvider('entriesOutsideTheRoot')]
    public function testAnEntryOutsideTheRootIsRefused(string $name): void
    {
        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The entry "'.$name.'" of the archive points outside its root.');

        new XlsxReader()->sheetNames($this->zip('slip.xlsx', [...Workbooks::xlsx(''), $name => 'x']));
    }

    public function testEntryNamesInsideTheRootAreAccepted(): void
    {
        foreach (['a..b/c.xml', 'x/..y', 'docs/c:d.xml', 'x/.../y'] as $name) {
            self::assertTrue(Archive::isInsideRoot($name), $name);
        }

        self::assertFalse(Archive::isInsideRoot(''));
        self::assertFalse(Archive::isInsideRoot("a\0b"));
        self::assertFalse(Archive::isInsideRoot('..'));
    }

    public function testATargetAboveTheRootIsRefused(): void
    {
        self::assertSame('xl/worksheets/a.xml', Archive::resolve('xl', 'worksheets/./a.xml'));
        self::assertSame('a.xml', Archive::resolve('xl/x', '/a.xml'));
        self::assertSame('a.xml', Archive::resolve('xl', '../a.xml'));
        self::assertSame('xl/a.xml', Archive::resolve('xl', 'x//../a.xml'));

        $entries = Workbooks::xlsx('');
        $entries['xl/_rels/workbook.xml.rels'] = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="worksheet" Target="../../etc/passwd"/></Relationships>';

        $this->expectExceptionMessageIsOrContains('The target "../../etc/passwd" points outside the archive.');

        new XlsxReader()->sheetNames($this->zip('target.xlsx', $entries));
    }

    public function testAMissingPartIsRefused(): void
    {
        $entries = Workbooks::xlsx('');
        unset($entries['xl/worksheets/sheet1.xml']);

        $this->expectExceptionMessageIsOrContains('The archive has no part "xl/worksheets/sheet1.xml".');

        iterator_to_array(new XlsxReader()->rows($this->zip('missing.xlsx', $entries)));
    }

    public function testAnEncryptedPartIsRefused(): void
    {
        $path = $this->path('encrypted.xlsx');
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        foreach (Workbooks::xlsx('') as $name => $bytes) {
            $zip->addFromString($name, $bytes);
        }

        $zip->setEncryptionName('xl/workbook.xml', ZipArchive::EM_AES_256, 'secret');
        $zip->close();

        $this->expectExceptionMessageIsOrContains('The part "xl/workbook.xml" is encrypted.');

        new XlsxReader()->sheetNames($path);
    }

    public function testTheRowsAreReadAsTheyAreTaken(): void
    {
        $rows = new XlsxReader()->rows($this->zip('book.xlsx', Workbooks::xlsx('<row><c><v>1</v></c></row><row><c t="x"/></row><row><c t="x"><v>1</v></c></row>')));

        self::assertSame(1, $rows->current()->number);

        $this->expectException(ReadRefused::class);

        $rows->next();
    }
}
