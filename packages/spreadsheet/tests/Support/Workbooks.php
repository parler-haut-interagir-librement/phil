<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Tests\Support;

use DateTimeImmutable;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Row;

/**
 * Hand-written parts of XLSX and ODS files, and a readable form of the rows read.
 */
final class Workbooks
{
    public const string MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public const string RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    public const string ODS_NAMESPACES = 'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"';

    /**
     * The entries of an XLSX workbook whose first sheet holds the XML given inside `<sheetData>`.
     *
     * @param array<string, string> $extra more entries, or entries replaced, by name
     *
     * @return array<string, string>
     */
    public static function xlsx(string $sheetData, array $extra = [], string $workbookPr = ''): array
    {
        return [
            '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="'.self::RELATIONSHIPS.'/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0"?><workbook xmlns="'.self::MAIN.'" xmlns:r="'.self::RELATIONSHIPS.'">'.$workbookPr.'<sheets><sheet name="First" sheetId="1" r:id="rId1"/><sheet name="Second" sheetId="2" r:id="rId2"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="'.self::RELATIONSHIPS.'/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="'.self::RELATIONSHIPS.'/worksheet" Target="/xl/worksheets/sheet2.xml"/>'
                .'<Relationship Id="rId3" Type="'.self::RELATIONSHIPS.'/sharedStrings" Target="sharedStrings.xml"/>'
                .'<Relationship Id="rId4" Type="'.self::RELATIONSHIPS.'/styles" Target="styles.xml"/>'
                .'<Relationship Id="rId5" Type="'.self::RELATIONSHIPS.'/hyperlink" Target="https://example.org/" TargetMode="External"/>'
                .'</Relationships>',
            'xl/sharedStrings.xml' => '<?xml version="1.0"?><sst xmlns="'.self::MAIN.'"><si><t>zero</t></si><si><r><t>one </t></r><r><t>rich</t></r><rPh><t>phonetic</t></rPh></si><si/></sst>',
            'xl/styles.xml' => '<?xml version="1.0"?><styleSheet xmlns="'.self::MAIN.'"><numFmts><numFmt numFmtId="164" formatCode="dd/mm/yyyy"/><numFmt numFmtId="165" formatCode="0.00"/></numFmts>'
                .'<cellStyleXfs><xf numFmtId="14"/></cellStyleXfs>'
                .'<cellXfs><xf numFmtId="0"/><xf numFmtId="14"/><xf numFmtId="22"/><xf numFmtId="164"/><xf numFmtId="165"/></cellXfs></styleSheet>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0"?><worksheet xmlns="'.self::MAIN.'"><sheetData>'.$sheetData.'</sheetData></worksheet>',
            'xl/worksheets/sheet2.xml' => '<?xml version="1.0"?><worksheet xmlns="'.self::MAIN.'"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>second</t></is></c></row></sheetData></worksheet>',
            ...$extra,
        ];
    }

    /**
     * An ODS `content.xml` whose spreadsheet holds the tables given.
     */
    public static function odsContent(string $tables): string
    {
        return '<?xml version="1.0"?><office:document-content '.self::ODS_NAMESPACES.'><office:body><office:spreadsheet>'.$tables.'</office:spreadsheet></office:body></office:document-content>';
    }

    /**
     * @param iterable<Row> $rows
     *
     * @return array<int, list<string>> each row's cells as `type:value`, by row number
     */
    public static function dump(iterable $rows): array
    {
        $dump = [];

        foreach ($rows as $row) {
            $dump[$row->number] = array_map(self::cell(...), $row->cells);
        }

        return $dump;
    }

    public static function cell(Cell $cell): string
    {
        $value = $cell->value;

        return $cell->type->value.':'.match (true) {
            $value instanceof DateTimeImmutable => $value->format('Y-m-d\TH:i:s.vP'),
            \is_bool($value) => $value ? 'true' : 'false',
            \is_float($value) => var_export($value, true),
            default => (string) $value,
        };
    }
}
