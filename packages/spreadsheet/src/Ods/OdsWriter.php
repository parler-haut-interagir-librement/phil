<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Ods;

use DateTimeImmutable;
use Override;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\CellType;
use Phil\Spreadsheet\Contract\SpreadsheetWriter;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\BufferedFile;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Internal\SheetRows;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;

/**
 * Writes a workbook as an OpenDocument spreadsheet (ODS, version 1.3): the content is written as the rows are read to
 * a temporary file, in chunks, then zipped with ext-zip — the `mimetype` first and stored, as the format requires —
 * and copied to the stream. Memory stays that of a chunk whatever the number of rows; the stream receives nothing
 * when writing fails, and the temporary files are removed either way.
 *
 * Every cell carries its type: a text is a string cell, which a spreadsheet never runs as a formula, so the guard
 * against formulas has nothing to change in this format. Numbers, booleans and dates keep their kind, dates formatted
 * `yyyy-mm-dd` or `yyyy-mm-dd hh:mm:ss`. The heading row is bold. A sheet holds at most 1,048,576 rows and 16,384
 * columns.
 */
final readonly class OdsWriter implements SpreadsheetWriter
{
    public const int ROW_LIMIT = 1_048_576;

    public const int COLUMN_LIMIT = 16_384;

    public const string MEDIA_TYPE = 'application/vnd.oasis.opendocument.spreadsheet';

    private const string DECLARATION = '<?xml version="1.0" encoding="UTF-8"?>'."\n";

    private const string NAMESPACES = 'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"'
        .' xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"'
        .' xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"'
        .' xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0"'
        .' xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0"'
        .' xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0"';

    /** The width of a character of the default font, in centimetres, with its share of the cell's margins. */
    private const float CHARACTER_WIDTH = 0.2;

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
        return Format::Ods;
    }

    #[Override]
    public function write(Workbook $workbook, mixed $stream, WriteOptions $options = new WriteOptions()): void
    {
        $stream = Output::stream($stream);
        $temporary = [];

        try {
            $temporary[] = $content = Output::temporary($this->directory);
            $this->content($workbook, $options, $content);
            $temporary[] = $archive = Output::temporary($this->directory);
            Output::zip($archive, [
                'mimetype' => self::MEDIA_TYPE,
                'META-INF/manifest.xml' => $this->manifest(),
                'styles.xml' => self::DECLARATION.'<office:document-styles '.self::NAMESPACES.' office:version="1.3"><office:styles/></office:document-styles>',
            ], ['content.xml' => $content], storeFirst: true);
            Output::copy($archive, $stream);
        } finally {
            Output::remove(...$temporary);
        }
    }

    private function content(Workbook $workbook, WriteOptions $options, string $path): void
    {
        $file = new BufferedFile($path);

        try {
            $widths = [];

            foreach ($workbook->sheets as $sheet) {
                foreach ($sheet->columns as $column) {
                    if (null !== $column->width) {
                        $widths['w'.Values::number($column->width)] ??= ['co'.(\count($widths) + 1), $column->width];
                    }
                }
            }

            $file->write(self::DECLARATION.'<office:document-content '.self::NAMESPACES.' office:version="1.3">'.$this->automaticStyles($widths).'<office:body><office:spreadsheet>');

            foreach ($workbook->sheets as $sheet) {
                $file->write('<table:table table:name="'.htmlspecialchars($sheet->name, \ENT_XML1 | \ENT_QUOTES, 'UTF-8').'">');

                foreach ($sheet->columns as $column) {
                    $file->write(null === $column->width ? '<table:table-column/>' : '<table:table-column table:style-name="'.$widths['w'.Values::number($column->width)][0].'"/>');
                }

                $number = 0;
                $headings = SheetRows::headings($sheet, $options);

                if (null !== $headings) {
                    ++$number;
                    $file->write($this->row($headings, ' table:style-name="ce3"'));
                }

                foreach (SheetRows::data($sheet, $options) as $cells) {
                    if (++$number > $this->rowLimit) {
                        throw WriteFailed::because(\sprintf('An ODS sheet holds at most %d rows, headings included.', $this->rowLimit));
                    }

                    $file->write($this->row($cells, ''));
                }

                $file->write('</table:table>');
            }

            $file->write('</office:spreadsheet></office:body></office:document-content>');
            $file->close();
        } finally {
            $file->discard();
        }
    }

    /**
     * @param list<Cell> $cells
     */
    private function row(array $cells, string $textStyle): string
    {
        if (\count($cells) > self::COLUMN_LIMIT) {
            throw WriteFailed::because(\sprintf('An ODS sheet holds at most %d columns.', self::COLUMN_LIMIT));
        }

        while ([] !== $cells && end($cells)->isEmpty()) {
            array_pop($cells);
        }

        $xml = '<table:table-row>';

        foreach ($cells as $cell) {
            $value = $cell->value;

            $xml .= match (true) {
                null === $value => '<table:table-cell/>',
                \is_string($value) => '<table:table-cell'.$textStyle.' office:value-type="string">'.OdsText::paragraphs($value).'</table:table-cell>',
                \is_bool($value) => '<table:table-cell office:value-type="boolean" office:boolean-value="'.($value ? 'true' : 'false').'"/>',
                $value instanceof DateTimeImmutable => CellType::Date === $cell->type
                    ? '<table:table-cell table:style-name="ce1" office:value-type="date" office:date-value="'.Values::date($value).'"/>'
                    : '<table:table-cell table:style-name="ce2" office:value-type="date" office:date-value="'.Values::date($value, 'T').'"/>',
                default => '<table:table-cell office:value-type="float" office:value="'.Values::number($value).'"/>',
            };
        }

        return $xml.'</table:table-row>';
    }

    /**
     * The styles of the dates, of the headings and of the column widths.
     *
     * @param array<string, array{string, float}> $widths the name of each column style and its width in characters
     */
    private function automaticStyles(array $widths): string
    {
        $day = '<number:year number:style="long"/><number:text>-</number:text><number:month number:style="long"/><number:text>-</number:text><number:day number:style="long"/>';
        $xml = '<office:automatic-styles>'
            .'<number:date-style style:name="N1">'.$day.'</number:date-style>'
            .'<number:date-style style:name="N2">'.$day.'<number:text> </number:text><number:hours number:style="long"/><number:text>:</number:text><number:minutes number:style="long"/><number:text>:</number:text><number:seconds number:style="long"/></number:date-style>'
            .'<style:style style:name="ce1" style:family="table-cell" style:data-style-name="N1"/>'
            .'<style:style style:name="ce2" style:family="table-cell" style:data-style-name="N2"/>'
            .'<style:style style:name="ce3" style:family="table-cell"><style:text-properties fo:font-weight="bold"/></style:style>';

        foreach ($widths as [$name, $width]) {
            $xml .= '<style:style style:name="'.$name.'" style:family="table-column"><style:table-column-properties style:column-width="'.Values::number(round($width * self::CHARACTER_WIDTH, 3)).'cm"/></style:style>';
        }

        return $xml.'</office:automatic-styles>';
    }

    private function manifest(): string
    {
        return self::DECLARATION
            .'<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">'
            .'<manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="'.self::MEDIA_TYPE.'"/>'
            .'<manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>'
            .'<manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>'
            .'</manifest:manifest>';
    }
}
