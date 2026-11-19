<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

use Override;
use Phil\DocumentImport\Internal\Export\Contract\Writer;
use Phil\DocumentImport\Internal\Mark;

/**
 * An OpenDocument text written block after block: paragraphs and headings in the common styles an office suite knows,
 * character formatting through automatic text styles, lists with their own list styles, tables with their header
 * rows, pictures with their title and description, footnotes, links, page breaks and a table of contents the suite
 * fills; then the package with its `mimetype` first, manifest, content, styles and metadata.
 *
 * @internal
 */
final class OdtWriter implements Writer
{
    public const string NAMESPACES = 'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" '
        .'xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" '
        .'xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" '
        .'xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" '
        .'xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" '
        .'xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" '
        .'xmlns:xlink="http://www.w3.org/1999/xlink" '
        .'xmlns:dc="http://purl.org/dc/elements/1.1/" '
        .'xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" '
        .'xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"';

    private const array STYLES = ['quote' => 'Quotations', 'caption' => 'Caption', 'title' => 'Title', 'paragraph' => 'Text_20_body'];

    private const array FORMATS = ['decimal' => '1', 'lower-alpha' => 'a', 'upper-alpha' => 'A', 'lower-roman' => 'i', 'upper-roman' => 'I'];

    private const array BULLETS = ['•', '◦', '▪'];

    private string $body = '';

    /** @var array<string, array{string, string}> the name and XML of each automatic paragraph style, by its properties */
    private array $paragraphs = [];

    /** @var array<string, array{string, string}> the name and XML of each automatic text style, by its properties */
    private array $texts = [];

    /** @var list<string> */
    private array $lists = [];

    /** @var array<string, string> the pictures, by their path in the package */
    private array $media = [];

    /** @var list<string> the footnotes written, in order */
    private array $written = [];

    private int $tables = 0;

    /**
     * @param array<string, list<Run>> $notes the footnotes of the document, by identifier
     */
    public function __construct(private readonly array $notes = [])
    {
    }

    #[Override]
    public function paragraph(array $runs, ?string $alignment = null, string $style = 'paragraph'): void
    {
        $this->body .= '<text:p text:style-name="'.$this->paragraphStyle(self::STYLES[$style] ?? 'Text_20_body', $alignment).'">'.$this->runs($runs).'</text:p>';
    }

    #[Override]
    public function heading(int $level, array $runs, ?string $alignment = null): void
    {
        $this->body .= '<text:h text:style-name="'.$this->paragraphStyle('Heading_20_'.$level, $alignment).'" text:outline-level="'.$level.'">'.$this->runs($runs).'</text:h>';
    }

    #[Override]
    public function list(array $items, bool $ordered, ?string $counter, ?int $start): void
    {
        $name = 'L'.(\count($this->lists) + 1);
        $levels = '';

        for ($level = 1; $level <= 10; ++$level) {
            $alignment = '<style:list-level-properties text:list-level-position-and-space-mode="label-alignment"><style:list-level-label-alignment text:label-followed-by="listtab" '
                .'fo:text-indent="-0.25in" fo:margin-left="'.(0.5 * $level).'in"/></style:list-level-properties>';
            $levels .= $ordered
                ? '<text:list-level-style-number text:level="'.$level.'" style:num-format="'.(self::FORMATS[$counter ?? ''] ?? '1').'" style:num-suffix="."'
                    .(1 === $level && null !== $start ? ' text:start-value="'.max(0, $start).'"' : '').'>'.$alignment.'</text:list-level-style-number>'
                : '<text:list-level-style-bullet text:level="'.$level.'" text:bullet-char="'.self::BULLETS[($level - 1) % 3].'">'.$alignment.'</text:list-level-style-bullet>';
        }

        $this->lists[] = '<text:list-style style:name="'.$name.'">'.$levels.'</text:list-style>';
        $this->body .= '<text:list text:style-name="'.$name.'">'.$this->items($items).'</text:list>';
    }

    #[Override]
    public function code(string $code): void
    {
        $lines = array_map($this->text(...), explode("\n", str_replace("\r\n", "\n", $code)));
        $this->body .= '<text:p text:style-name="Preformatted_20_Text">'.implode('<text:line-break/>', $lines).'</text:p>';
    }

    #[Override]
    public function table(array $rows, bool $headings): void
    {
        if ([] === $rows) {
            return;
        }

        $width = max(array_map(\count(...), $rows));
        $xml = '<table:table table:name="Table'.++$this->tables.'"><table:table-column table:number-columns-repeated="'.$width.'"/>';

        foreach ($rows as $index => $row) {
            $cells = '';

            foreach (array_pad($row, $width, []) as $cell) {
                $cells .= '<table:table-cell office:value-type="string"><text:p text:style-name="'.($headings && 0 === $index ? 'Table_20_Heading' : 'Table_20_Contents').'">'.$this->runs($cell).'</text:p></table:table-cell>';
            }

            $row = '<table:table-row>'.$cells.'</table:table-row>';
            $xml .= $headings && 0 === $index ? '<table:table-header-rows>'.$row.'</table:table-header-rows>' : $row;
        }

        $this->body .= $xml.'</table:table>';
    }

    #[Override]
    public function image(Picture $picture): void
    {
        $number = \count($this->media) + 1;
        $path = 'Pictures/image'.$number.'.'.$picture->extension();
        $this->media[$path] = $picture->bytes;
        [$width, $height] = $picture->inches();
        $alt = XmlText::escape($picture->alt);

        $this->body .= '<text:p text:style-name="Text_20_body"><draw:frame draw:name="Image'.$number.'" text:anchor-type="as-char" svg:width="'.round($width, 4).'in" svg:height="'.round($height, 4).'in" draw:z-index="0">'
            .'<draw:image xlink:href="'.$path.'" xlink:type="simple" xlink:show="embed" xlink:actuate="onLoad"/>'
            .('' === $alt ? '' : '<svg:title>'.$alt.'</svg:title><svg:desc>'.$alt.'</svg:desc>').'</draw:frame></text:p>';
    }

    #[Override]
    public function pageBreak(): void
    {
        $this->body .= '<text:p text:style-name="'.$this->paragraphStyle('Text_20_body', null, true).'"/>';
    }

    #[Override]
    public function delimiter(): void
    {
        $this->paragraph([new Run('* * *')], 'center');
    }

    #[Override]
    public function toc(string $title): void
    {
        $heading = $this->text(Runs::text($title));

        $this->body .= '<text:table-of-content text:name="Table of Contents1"><text:table-of-content-source text:outline-level="3"><text:index-title-template text:style-name="Contents_20_Heading">'.$heading.'</text:index-title-template></text:table-of-content-source>'
            .'<text:index-body>'.('' === $heading ? '' : '<text:index-title text:name="Table of Contents1_Head"><text:p text:style-name="Contents_20_Heading">'.$heading.'</text:p></text:index-title>').'</text:index-body></text:table-of-content>';
    }

    #[Override]
    public function files(array $metadata): array
    {
        $automatic = implode('', array_column($this->paragraphs, 1)).implode('', array_column($this->texts, 1)).implode('', $this->lists);
        $manifest = '<manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>';

        foreach (['content.xml', 'styles.xml', 'meta.xml'] as $part) {
            $manifest .= '<manifest:file-entry manifest:full-path="'.$part.'" manifest:media-type="text/xml"/>';
        }

        foreach (array_keys($this->media) as $path) {
            $manifest .= '<manifest:file-entry manifest:full-path="'.$path.'" manifest:media-type="image/'.pathinfo($path, \PATHINFO_EXTENSION).('svg' === pathinfo($path, \PATHINFO_EXTENSION) ? '+xml' : '').'"/>';
        }

        return [
            'mimetype' => 'application/vnd.oasis.opendocument.text',
            'META-INF/manifest.xml' => XmlText::DECLARATION.'<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">'.$manifest.'</manifest:manifest>',
            'content.xml' => XmlText::DECLARATION.'<office:document-content '.self::NAMESPACES.' office:version="1.3"><office:automatic-styles>'.$automatic.'</office:automatic-styles>'
                .'<office:body><office:text>'.$this->body.'</office:text></office:body></office:document-content>',
            'styles.xml' => OdtStylesheet::STYLES,
            'meta.xml' => $this->meta($metadata),
            ...$this->media,
        ];
    }

    /**
     * @param list<ListItem> $items
     */
    private function items(array $items): string
    {
        $xml = '';

        foreach ($items as $item) {
            $runs = null === $item->checked ? $item->runs : [new Run($item->checked ? '☑ ' : '☐ '), ...$item->runs];
            $xml .= '<text:list-item><text:p text:style-name="Text_20_body">'.$this->runs($runs).'</text:p>'
                .([] === $item->items ? '' : '<text:list>'.$this->items($item->items).'</text:list>').'</text:list-item>';
        }

        return $xml;
    }

    /**
     * The automatic style of a paragraph of a common style aligned, or opening a page; the common style alone
     * otherwise.
     */
    private function paragraphStyle(string $parent, ?string $alignment, bool $pageBreak = false): string
    {
        $align = ['center' => 'center', 'right' => 'end', 'justify' => 'justify'][$alignment ?? ''] ?? null;

        if (null === $align && !$pageBreak) {
            return $parent;
        }

        $key = $parent.'|'.$align.'|'.($pageBreak ? 'break' : '');
        $name = 'P'.(\count($this->paragraphs) + 1);
        $this->paragraphs[$key] ??= [$name, '<style:style style:name="'.$name.'" style:family="paragraph" style:parent-style-name="'.$parent.'"><style:paragraph-properties'
            .(null === $align ? '' : ' fo:text-align="'.$align.'"').($pageBreak ? ' fo:break-before="page"' : '').'/></style:style>'];

        return $this->paragraphs[$key][0];
    }

    /**
     * @param list<Run> $runs
     */
    private function runs(array $runs): string
    {
        $xml = '';

        foreach ($runs as $run) {
            $xml .= match (true) {
                null !== $run->note => $this->note($run),
                $run->break => '<text:line-break/>',
                null !== $run->link => '<text:a xlink:type="simple" xlink:href="'.XmlText::escape($run->link).'">'.$this->span($run->text, $run->marks).'</text:a>',
                default => $this->span($run->text, $run->marks),
            };
        }

        return $xml;
    }

    /**
     * A footnote where it is first referenced; a reference to a footnote already written, or that the document does
     * not hold, stays its number in exponent.
     */
    private function note(Run $run): string
    {
        $id = $run->note ?? '';

        if (!isset($this->notes[$id]) || \in_array($id, $this->written, true)) {
            return $this->span($run->text, $run->marks);
        }

        $this->written[] = $id;
        $number = \count($this->written);

        return '<text:note text:id="ftn'.$number.'" text:note-class="footnote"><text:note-citation>'.$number.'</text:note-citation><text:note-body><text:p text:style-name="Footnote">'
            .$this->runs($this->notes[$id]).'</text:p></text:note-body></text:note>';
    }

    /**
     * @param list<Mark> $marks
     */
    private function span(string $text, array $marks): string
    {
        $text = $this->text($text);

        if ([] === $marks) {
            return $text;
        }

        $key = implode(',', array_map(static fn (Mark $mark): string => $mark->name, $marks));
        $name = 'T'.(\count($this->texts) + 1);
        $parent = \in_array(Mark::Code, $marks, true) ? ' style:parent-style-name="Source_20_Text"' : '';
        $this->texts[$key] ??= [$name, '<style:style style:name="'.$name.'" style:family="text"'.$parent.'><style:text-properties'.$this->properties($marks).'/></style:style>'];

        return '<text:span text:style-name="'.$this->texts[$key][0].'">'.$text.'</text:span>';
    }

    /**
     * @param list<Mark> $marks
     */
    private function properties(array $marks): string
    {
        $has = static fn (Mark $mark): bool => \in_array($mark, $marks, true);

        return ($has(Mark::Bold) ? ' fo:font-weight="bold"' : '')
            .($has(Mark::Italic) ? ' fo:font-style="italic"' : '')
            .($has(Mark::Underline) ? ' style:text-underline-style="solid" style:text-underline-width="auto" style:text-underline-color="font-color"' : '')
            .($has(Mark::Strikethrough) ? ' style:text-line-through-style="solid"' : '')
            .($has(Mark::Marker) ? ' fo:background-color="#ffff00"' : '')
            .($has(Mark::Superscript) ? ' style:text-position="super 58%"' : ($has(Mark::Subscript) ? ' style:text-position="sub 58%"' : ''));
    }

    /**
     * Text with its runs of spaces, tabs and line breaks written as OpenDocument writes them.
     */
    private function text(string $text): string
    {
        $escaped = XmlText::escape($text);
        $escaped = (string) preg_replace_callback('/ {2,}/', static fn (array $match): string => ' <text:s text:c="'.(\strlen($match[0]) - 1).'"/>', $escaped);
        $escaped = (string) preg_replace('/^ /', '<text:s/>', $escaped);

        return str_replace(["\t", "\n"], ['<text:tab/>', '<text:line-break/>'], $escaped);
    }

    /**
     * @param array<string, string> $metadata
     */
    private function meta(array $metadata): string
    {
        $elements = ['title' => 'dc:title', 'subject' => 'dc:subject', 'creator' => 'meta:initial-creator', 'keywords' => 'meta:keyword', 'description' => 'dc:description', 'language' => 'dc:language', 'created' => 'meta:creation-date', 'modified' => 'dc:date'];
        $xml = '';

        foreach ($elements as $key => $element) {
            if (isset($metadata[$key]) && '' !== trim($metadata[$key])) {
                $xml .= '<'.$element.'>'.XmlText::escape($metadata[$key]).'</'.$element.'>';
            }
        }

        return XmlText::DECLARATION.'<office:document-meta '.self::NAMESPACES.' office:version="1.3"><office:meta>'.$xml.'</office:meta></office:document-meta>';
    }
}
