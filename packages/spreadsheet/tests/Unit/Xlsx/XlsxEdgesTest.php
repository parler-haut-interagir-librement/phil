<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Unit\Xlsx;

use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Tests\Support\TemporaryFiles;
use Phil\Spreadsheet\Tests\Support\Workbooks;
use Phil\Spreadsheet\Xlsx\XlsxReader;
use Phil\Spreadsheet\Xlsx\XlsxSheetParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The corners of the workbook, its relationships, its shared texts, its styles and its sheets: what a reader meets in
 * files other programs write.
 */
#[CoversClass(XlsxReader::class)]
#[CoversClass(XlsxSheetParser::class)]
final class XlsxEdgesTest extends TestCase
{
    use TemporaryFiles;

    /**
     * @param array<string, string> $extra
     *
     * @return array<int, list<string>>
     */
    private function read(string $sheetData, array $extra = [], string $workbookPr = ''): array
    {
        return Workbooks::dump(new XlsxReader()->rows($this->zip('book.xlsx', Workbooks::xlsx($sheetData, $extra, $workbookPr))));
    }

    private function refused(string $sheetData, string $message): void
    {
        try {
            $this->read($sheetData);
            self::fail('The workbook is refused.');
        } catch (ReadRefused $readRefused) {
            self::assertSame($message, $readRefused->getMessage());
        }
    }

    private function workbook(string $sheets): string
    {
        return '<?xml version="1.0"?><workbook xmlns="'.Workbooks::MAIN.'" xmlns:r="'.Workbooks::RELATIONSHIPS.'"><!-- a comment --><sheets>'.$sheets.'</sheets></workbook>';
    }

    public function testACommentBeforeTheSheetsIsSkipped(): void
    {
        $path = $this->zip('book.xlsx', Workbooks::xlsx('', ['xl/workbook.xml' => $this->workbook('<sheet name="First" sheetId="1" r:id="rId1"/>')]));

        self::assertSame(['First'], new XlsxReader()->sheetNames($path));
    }

    public function testASheetWithoutAPartIsRefused(): void
    {
        foreach (['<sheet name="Lost" sheetId="3"/>', '<sheet name="Lost" sheetId="3" r:id="rId5"/>'] as $sheet) {
            $path = $this->zip('book.xlsx', Workbooks::xlsx('', ['xl/workbook.xml' => $this->workbook($sheet)]));

            try {
                new XlsxReader()->sheetNames($path);
                self::fail('The sheet has no part.');
            } catch (ReadRefused $readRefused) {
                self::assertSame('The part "xl/workbook.xml" names the sheet "Lost" without its part.', $readRefused->getMessage());
            }
        }
    }

    public function testASheetWithoutANameIsRefusedWithAMessage(): void
    {
        $path = $this->zip('book.xlsx', Workbooks::xlsx('', ['xl/workbook.xml' => $this->workbook('<sheet sheetId="3" r:id="rId9"/>')]));

        $this->expectException(ReadRefused::class);
        $this->expectExceptionMessageIsOrContains('The part "xl/workbook.xml" names the sheet "" without its part.');
        new XlsxReader()->sheetNames($path);
    }

    public function testASheetNameInAMessageIsCutToAHundredCharacters(): void
    {
        $name = 'a'.str_repeat('é', 150);
        $path = $this->zip('book.xlsx', Workbooks::xlsx('', ['xl/workbook.xml' => $this->workbook('<sheet name="'.$name.'" sheetId="3" r:id="rId9"/>')]));

        try {
            new XlsxReader()->sheetNames($path);
            self::fail('The sheet has no part.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The part "xl/workbook.xml" names the sheet "a'.str_repeat('é', 99).'" without its part.', $readRefused->getMessage());
        }

        try {
            iterator_to_array(new XlsxReader()->rows($this->zip('other.xlsx', Workbooks::xlsx('')), 'a'.str_repeat('é', 150)));
            self::fail('The workbook has no such sheet.');
        } catch (ReadRefused $readRefused) {
            self::assertSame('The workbook has no sheet "a'.str_repeat('é', 99).'".', $readRefused->getMessage());
        }
    }

    public function testARelationshipWithoutATypeIsKept(): void
    {
        $relationships = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="'.Workbooks::RELATIONSHIPS.'/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Target="worksheets/sheet2.xml"/>'
            .'<Relationship Target="nothing.xml"/>'
            .'</Relationships>';
        $path = $this->zip('book.xlsx', Workbooks::xlsx('', ['xl/_rels/workbook.xml.rels' => $relationships]));

        self::assertSame(['First'], new XlsxReader()->sheetNames($path));
    }

    public function testASharedTextAfterAPhoneticRunIsRead(): void
    {
        $shared = '<?xml version="1.0"?><sst xmlns="'.Workbooks::MAIN.'"><si><rPh sb="0"><t>p</t></rPh><t>x</t></si><si><rPh/><t>y</t></si><si><t>z</t></si></sst>';

        self::assertSame([1 => ['text:x', 'text:y', 'text:z']], $this->read(
            '<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>',
            ['xl/sharedStrings.xml' => $shared],
        ));
    }

    public function testAStyleAfterTheCellFormatsIsNotACellFormat(): void
    {
        $styles = '<?xml version="1.0"?><styleSheet xmlns="'.Workbooks::MAIN.'"><cellXfs><xf numFmtId="0"/></cellXfs><cellStyleXfs><xf numFmtId="14"/></cellStyleXfs></styleSheet>';

        self::assertSame([1 => ['number:45293']], $this->read('<row r="1"><c r="A1" s="1"><v>45293</v></c></row>', ['xl/styles.xml' => $styles]));
    }

    public function testACellFormatWithAChildDoesNotEndTheCellFormats(): void
    {
        $styles = '<?xml version="1.0"?><styleSheet xmlns="'.Workbooks::MAIN.'"><cellXfs><xf numFmtId="0"><alignment/></xf><xf numFmtId="14"/></cellXfs></styleSheet>';

        self::assertSame([1 => ['date:2024-01-02T00:00:00.000+00:00']], $this->read('<row r="1"><c r="A1" s="1"><v>45293</v></c></row>', ['xl/styles.xml' => $styles]));
    }

    public function testANumberFormatWithoutACodeIsNotADate(): void
    {
        $styles = '<?xml version="1.0"?><styleSheet xmlns="'.Workbooks::MAIN.'"><numFmts><numFmt numFmtId="14"/></numFmts><cellXfs><xf numFmtId="14"/></cellXfs></styleSheet>';

        self::assertSame([1 => ['number:45293']], $this->read('<row r="1"><c r="A1" s="0"><v>45293</v></c></row>', ['xl/styles.xml' => $styles]));
    }

    public function testAnEmptyValueBeforeWhitespaceIsAnEmptyCell(): void
    {
        self::assertSame([1 => ['text:a']], $this->read('<row r="1"><c r="A1" t="inlineStr"><is><t>a</t></is></c><c r="B1"><v/>  </c></row>'));
    }

    public function testAPhoneticRunDoesNotHideTheTextAfterIt(): void
    {
        self::assertSame([1 => ['text:x']], $this->read('<row r="1"><c r="A1" t="inlineStr"><is><rPh><t>p</t></rPh><t>x</t></is></c></row>'));
        self::assertSame([1 => ['text:x']], $this->read('<row r="1"><c r="A1" t="inlineStr"><is><rPh/><t>x</t></is></c></row>'));
        self::assertSame([1 => ['text:c']], $this->read('<row r="1"><c r="A1" t="inlineStr"><is><rPh><rPh><t>a</t></rPh><t>b</t></rPh><t>c</t></is></c></row>'));
    }

    public function testTheTextNodesOfAValueAreJoined(): void
    {
        self::assertSame([1 => ['number:12']], $this->read('<row r="1"><c r="A1"><v>1<![CDATA[2]]></v></c></row>'));
    }

    public function testTheWhitespaceBetweenTheTextsOfAnInlineStringIsNotText(): void
    {
        self::assertSame([1 => ['text:ab']], $this->read("<row r=\"1\"><c r=\"A1\" t=\"inlineStr\"><is><t>a</t>\n<t>b</t></is></c></row>"));
    }

    public function testARowNumberIsDigitsFromOne(): void
    {
        foreach (['1&#10;', 'x5', '12345678901', '0', ''] as $number) {
            $this->refused('<row r="'.$number.'"><c r="A1"><v>1</v></c></row>', 'The part "xl/worksheets/sheet1.xml" has a row without a number.');
        }
    }

    public function testASharedTextIndexIsDigitsOnly(): void
    {
        foreach (['0&#10;', 'x0', '0x', ''] as $index) {
            $this->refused('<row r="1"><c r="A1" t="s"><v>'.$index.'</v></c></row>', 'The part "xl/worksheets/sheet1.xml" refers to a shared text it does not have.');
        }
    }
}
