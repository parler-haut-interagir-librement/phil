<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Xlsx;

use DateTimeImmutable;
use Override;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\CellType;
use Phil\Spreadsheet\Contract\SpreadsheetWriter;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\BufferedFile;
use Phil\Spreadsheet\Internal\FormulaGuard;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Internal\SheetRows;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\Sheet;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;

/**
 * Writes a workbook as Office Open XML (XLSX): each sheet's rows are written as they are read to a temporary file, in
 * chunks, then the parts are zipped with ext-zip and copied to the stream. Memory stays that of a chunk whatever the
 * number of rows; the stream receives nothing when writing fails, and the temporary files are removed either way.
 *
 * A text is an inline string, never a formula; a text a spreadsheet would run as one when edited is marked with the
 * format's quote prefix, which keeps it text, unless the options turn the guard off. Numbers and booleans keep their
 * kind; dates are serial numbers formatted `yyyy-mm-dd` or `yyyy-mm-dd hh:mm:ss`, from 1900-03-01. The heading row is
 * bold and frozen. A sheet holds at most 1,048,576 rows and 16,384 columns, a text 32,767 characters.
 */
final readonly class XlsxWriter implements SpreadsheetWriter
{
    public const int ROW_LIMIT = 1_048_576;

    public const int COLUMN_LIMIT = 16_384;

    private const string MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const string RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const string PACKAGE_RELATIONSHIPS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const string DECLARATION = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";

    private const int STYLE_DATE = 1;

    private const int STYLE_DATE_TIME = 2;

    private const int STYLE_QUOTED = 3;

    private const int STYLE_HEADING = 4;

    private const int STYLE_QUOTED_HEADING = 5;

    private string $directory;

    /**
     * @param string|null $temporaryDirectory where the temporary files go, the system's temporary directory when null
     * @param int         $rowLimit           the most rows a sheet may have, headings included, up to the format's limit
     *
     * @throws InvalidSpreadsheet when the limit is not between 1 and 1,048,576
     */
    public function __construct(
        ?string $temporaryDirectory = null,
        private int $rowLimit = self::ROW_LIMIT,
    ) {
        if ($rowLimit < 1 || $rowLimit > self::ROW_LIMIT) {
            throw InvalidSpreadsheet::because(\sprintf('The most rows of a sheet is between 1 and %d.', self::ROW_LIMIT));
        }

        $this->directory = $temporaryDirectory ?? sys_get_temp_dir();
    }

    #[Override]
    public function format(): Format
    {
        return Format::Xlsx;
    }

    #[Override]
    public function write(Workbook $workbook, mixed $stream, WriteOptions $options = new WriteOptions()): void
    {
        $stream = Output::stream($stream);
        $temporary = [];

        try {
            $files = [];

            foreach ($workbook->sheets as $index => $sheet) {
                $temporary[] = $path = Output::temporary($this->directory);
                $this->worksheet($sheet, $options, $path);
                $files['xl/worksheets/sheet'.($index + 1).'.xml'] = $path;
            }

            $temporary[] = $archive = Output::temporary($this->directory);
            Output::zip($archive, $this->parts($workbook), $files);
            Output::copy($archive, $stream);
        } finally {
            Output::remove(...$temporary);
        }
    }

    private function worksheet(Sheet $sheet, WriteOptions $options, string $path): void
    {
        $file = new BufferedFile($path);

        try {
            $headings = SheetRows::headings($sheet, $options);
            $view = null === $headings ? '<sheetView workbookViewId="0"/>' : '<sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView>';
            $file->write(self::DECLARATION.'<worksheet xmlns="'.self::MAIN.'" xmlns:r="'.self::RELATIONSHIPS.'"><sheetViews>'.$view.'</sheetViews>'.$this->columns($sheet).'<sheetData>');
            /** @var array<int, string> $letters */
            $letters = [];
            $number = 0;

            if (null !== $headings) {
                $file->write($this->row(++$number, $headings, $options->guardFormulas, true, $letters));
            }

            foreach (SheetRows::data($sheet, $options) as $cells) {
                if ($number >= $this->rowLimit) {
                    throw WriteFailed::because(\sprintf('An XLSX sheet holds at most %d rows, headings included.', $this->rowLimit));
                }

                $file->write($this->row(++$number, $cells, $options->guardFormulas, false, $letters));
            }

            $file->write('</sheetData></worksheet>');
            $file->close();
        } finally {
            $file->discard();
        }
    }

    private function columns(Sheet $sheet): string
    {
        $xml = '';

        foreach ($sheet->columns as $index => $column) {
            if (null !== $column->width) {
                $xml .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.Values::number($column->width).'" customWidth="1"/>';
            }
        }

        return '' === $xml ? '' : '<cols>'.$xml.'</cols>';
    }

    /**
     * @param list<Cell>         $cells
     * @param array<int, string> $letters the letters of the columns met so far, by index
     */
    private function row(int $number, array $cells, bool $guardFormulas, bool $heading, array &$letters): string
    {
        if (\count($cells) > self::COLUMN_LIMIT) {
            throw WriteFailed::because(\sprintf('An XLSX sheet holds at most %d columns.', self::COLUMN_LIMIT));
        }

        $xml = '<row r="'.$number.'">';

        foreach ($cells as $index => $cell) {
            $value = $cell->value;

            if (null === $value) {
                continue;
            }

            $reference = ($letters[$index] ??= CellReference::letters($index + 1)).$number;
            $xml .= \is_string($value) ? $this->text($reference, $value, $guardFormulas, $heading) : $this->value($reference, $cell, $value);
        }

        return $xml.'</row>';
    }

    private function text(string $reference, string $value, bool $guardFormulas, bool $heading): string
    {
        if (!XlsxText::fits($value)) {
            throw WriteFailed::because(\sprintf('The cell %s holds more than the %d characters of an XLSX text.', $reference, XlsxText::LENGTH));
        }

        $quoted = $guardFormulas && FormulaGuard::isRisky($value);
        $style = $heading ? ($quoted ? self::STYLE_QUOTED_HEADING : self::STYLE_HEADING) : ($quoted ? self::STYLE_QUOTED : 0);
        $space = trim($value) === $value ? '' : ' xml:space="preserve"';

        return '<c r="'.$reference.'"'.(0 === $style ? '' : ' s="'.$style.'"').' t="inlineStr"><is><t'.$space.'>'.XlsxText::escape($value).'</t></is></c>';
    }

    private function value(string $reference, Cell $cell, int|float|bool|DateTimeImmutable $value): string
    {
        return match (true) {
            \is_bool($value) => '<c r="'.$reference.'" t="b"><v>'.($value ? '1' : '0').'</v></c>',
            $value instanceof DateTimeImmutable => '<c r="'.$reference.'" s="'.(CellType::Date === $cell->type ? self::STYLE_DATE : self::STYLE_DATE_TIME).'"><v>'.Values::number(ExcelDate::serial($value)).'</v></c>',
            default => '<c r="'.$reference.'"><v>'.Values::number($value).'</v></c>',
        };
    }

    /**
     * The parts held as text, in their order in the archive.
     *
     * @return array<string, string>
     */
    private function parts(Workbook $workbook): array
    {
        $overrides = '';
        $sheets = '';
        $relationships = [];

        foreach ($workbook->sheets as $index => $sheet) {
            $id = $index + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheets .= '<sheet name="'.XlsxText::escape($sheet->name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
            $relationships['rId'.$id] = ['worksheet', 'worksheets/sheet'.$id.'.xml'];
        }

        $relationships['rId'.(\count($workbook->sheets) + 1)] = ['styles', 'styles.xml'];

        return [
            '[Content_Types].xml' => self::DECLARATION
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .$overrides
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'</Types>',
            '_rels/.rels' => $this->relationships(['rId1' => ['officeDocument', 'xl/workbook.xml']]),
            'xl/workbook.xml' => self::DECLARATION
                .'<workbook xmlns="'.self::MAIN.'" xmlns:r="'.self::RELATIONSHIPS.'"><sheets>'.$sheets.'</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => $this->relationships($relationships),
            'xl/styles.xml' => $this->styles(),
        ];
    }

    /**
     * @param array<string, array{string, string}> $targets type and target, by identifier
     */
    private function relationships(array $targets): string
    {
        $xml = self::DECLARATION.'<Relationships xmlns="'.self::PACKAGE_RELATIONSHIPS.'">';

        foreach ($targets as $id => [$type, $target]) {
            $xml .= '<Relationship Id="'.$id.'" Type="'.self::RELATIONSHIPS.'/'.$type.'" Target="'.$target.'"/>';
        }

        return $xml.'</Relationships>';
    }

    /**
     * The styles, by index: 0 the default, 1 a date, 2 a date and time, 3 a quoted text, 4 a heading, 5 a quoted heading.
     */
    private function styles(): string
    {
        $xf = '<xf numFmtId="%d" fontId="%d" fillId="0" borderId="0" xfId="0"%s/>';

        return self::DECLARATION
            .'<styleSheet xmlns="'.self::MAIN.'">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm:ss"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/></font><font><b/><sz val="11"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="6">'
            .\sprintf($xf, 0, 0, '')
            .\sprintf($xf, 164, 0, ' applyNumberFormat="1"')
            .\sprintf($xf, 165, 0, ' applyNumberFormat="1"')
            .\sprintf($xf, 0, 0, ' quotePrefix="1"')
            .\sprintf($xf, 0, 1, ' applyFont="1"')
            .\sprintf($xf, 0, 1, ' applyFont="1" quotePrefix="1"')
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
