<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Xlsx;

use Override;
use Phil\DataTableExport\Contract\ExportWriter;
use Phil\DataTableExport\Exception\ExportRefused;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;
use ZipArchive;

/**
 * Writes a sheet as an Office Open XML workbook of one worksheet, zipped with ext-zip. The rows are written as they are read
 * to a temporary file, in chunks, then zipped and copied to the stream: memory stays that of a chunk whatever the
 * number of rows, and the temporary files are removed even when writing fails.
 *
 * The headings are bold and frozen above the rows. A text is an inline string, never a formula; a number, a boolean
 * and a date keep their kind, the date with a date format. The title names the worksheet, kept to the 31 characters
 * and the characters a worksheet name allows. A sheet has at most 1,048,576 rows, headings included — fewer when the
 * writer is given a lower limit — and 16,384 columns, as a spreadsheet does.
 */
final readonly class XlsxWriter implements ExportWriter
{
    public const int ROW_LIMIT = 1048576;

    public const int COLUMN_LIMIT = 16384;

    /** The bytes gathered before they are written to the temporary file. */
    private const int CHUNK = 65536;

    /** 1980-01-01, the first moment a zip entry dates: every entry carries it, so the same sheet gives the same bytes. */
    private const int ENTRY_TIME = 315532800;

    private const string MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const string RELATIONSHIPS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const string PACKAGE_RELATIONSHIPS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private string $directory;

    /**
     * @param string|null $directory where the temporary files go, the system's temporary directory when null
     * @param int         $rowLimit  the most rows a sheet may have, headings included, up to the spreadsheet's limit
     *
     * @throws ExportRefused when the limit is not between 1 and 1,048,576
     */
    public function __construct(
        ?string $directory = null,
        private int $rowLimit = self::ROW_LIMIT,
    ) {
        if ($rowLimit < 1 || $rowLimit > self::ROW_LIMIT) {
            throw ExportRefused::because(\sprintf('The most rows of a sheet is between 1 and %d.', self::ROW_LIMIT));
        }

        $this->directory = $directory ?? sys_get_temp_dir();
    }

    #[Override]
    public function format(): Format
    {
        return Format::Xlsx;
    }

    #[Override]
    public function write(Sheet $sheet, mixed $stream): void
    {
        $worksheet = $this->temporary();
        $archive = $this->temporary();

        try {
            $this->worksheet($sheet, $worksheet);
            $this->zip($sheet, $worksheet, $archive);
            $this->copy($archive, $stream);
        } finally {
            @unlink($worksheet);
            @unlink($archive);
        }
    }

    /**
     * A worksheet's name: without `[ ] : * ? / \`, without an apostrophe at either end, at most 31 characters, `Sheet1`
     * when nothing is left.
     */
    public static function worksheetName(string $title): string
    {
        $name = trim(mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\\x00-\x1F]/u', ' ', mb_scrub($title, 'UTF-8')), " '"), 0, 31), " '");

        return '' === $name ? 'Sheet1' : $name;
    }

    private function worksheet(Sheet $sheet, string $path): void
    {
        $file = @fopen($path, 'w');

        if (false === $file) {
            throw ExportRefused::because('The temporary file of the worksheet cannot be opened.');
        }

        try {
            $frozen = [] === $sheet->headings ? '<sheetView workbookViewId="0"/>' : '<sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView>';
            $buffer = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
                .'<worksheet xmlns="'.self::MAIN.'" xmlns:r="'.self::RELATIONSHIPS.'"><sheetViews>'.$frozen.'</sheetViews><sheetData>';
            $number = 0;

            if ([] !== $sheet->headings) {
                $buffer .= $this->row(++$number, $sheet->headings, SheetXml::HEADING_STYLE);
            }

            foreach ($sheet->rows as $row) {
                if ($number >= $this->rowLimit) {
                    throw ExportRefused::because(\sprintf('A sheet holds at most %d rows, headings included.', $this->rowLimit));
                }

                $buffer .= $this->row(++$number, $row);

                if (\strlen($buffer) >= self::CHUNK) {
                    $this->put($file, $buffer);
                    $buffer = '';
                }
            }

            $this->put($file, $buffer.'</sheetData></worksheet>');
        } finally {
            fclose($file);
        }
    }

    /**
     * @param list<mixed> $cells
     */
    private function row(int $number, array $cells, int $style = 0): string
    {
        if (\count($cells) > self::COLUMN_LIMIT) {
            throw ExportRefused::because(\sprintf('A sheet holds at most %d columns.', self::COLUMN_LIMIT));
        }

        $xml = '<row r="'.$number.'">';

        foreach ($cells as $index => $cell) {
            $xml .= SheetXml::cell(SheetXml::column($index + 1).$number, $cell, $style);
        }

        return $xml.'</row>';
    }

    private function zip(Sheet $sheet, string $worksheet, string $path): void
    {
        $zip = new ZipArchive();

        if (true !== $zip->open($path, ZipArchive::OVERWRITE)) {
            throw ExportRefused::because('The temporary file of the workbook cannot be opened.');
        }

        $parts = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->relationships(['rId1' => ['officeDocument', 'xl/workbook.xml']]),
            'xl/workbook.xml' => $this->workbook($sheet),
            'xl/_rels/workbook.xml.rels' => $this->relationships(['rId1' => ['worksheet', 'worksheets/sheet1.xml'], 'rId2' => ['styles', 'styles.xml']]),
            'xl/styles.xml' => $this->styles(),
        ];

        foreach ($parts as $name => $xml) {
            $zip->addFromString($name, $xml);
            $zip->setMtimeName($name, self::ENTRY_TIME);
        }

        $zip->addFile($worksheet, 'xl/worksheets/sheet1.xml');
        $zip->setMtimeName('xl/worksheets/sheet1.xml', self::ENTRY_TIME);

        if (!$zip->close()) {
            throw ExportRefused::because('The workbook cannot be written to its temporary file.');
        }
    }

    /**
     * @param resource $stream
     */
    private function copy(string $archive, mixed $stream): void
    {
        $file = @fopen($archive, 'r');

        if (false === $file) {
            throw ExportRefused::because('The temporary file of the workbook cannot be read.');
        }

        try {
            if (@stream_copy_to_stream($file, $stream) !== filesize($archive)) {
                throw ExportRefused::because('The stream refused the workbook.');
            }
        } finally {
            fclose($file);
        }
    }

    private function temporary(): string
    {
        $path = @tempnam($this->directory, 'phil-xlsx-');

        if (false === $path) {
            throw ExportRefused::because(\sprintf('No temporary file can be made in "%s".', $this->directory));
        }

        return $path;
    }

    /**
     * @param resource $file
     */
    private function put(mixed $file, string $bytes): void
    {
        if (\strlen($bytes) !== @fwrite($file, $bytes)) {
            throw ExportRefused::because('The temporary file of the worksheet refused its rows.');
        }
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    /**
     * @param array<string, array{string, string}> $targets type and target, by identifier
     */
    private function relationships(array $targets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".'<Relationships xmlns="'.self::PACKAGE_RELATIONSHIPS.'">';

        foreach ($targets as $id => [$type, $target]) {
            $xml .= '<Relationship Id="'.$id.'" Type="'.self::RELATIONSHIPS.'/'.$type.'" Target="'.$target.'"/>';
        }

        return $xml.'</Relationships>';
    }

    private function workbook(Sheet $sheet): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<workbook xmlns="'.self::MAIN.'" xmlns:r="'.self::RELATIONSHIPS.'"><sheets>'
            .'<sheet name="'.SheetXml::text(self::worksheetName($sheet->title)).'" sheetId="1" r:id="rId1"/>'
            .'</sheets></workbook>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n"
            .'<styleSheet xmlns="'.self::MAIN.'">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm:ss"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
