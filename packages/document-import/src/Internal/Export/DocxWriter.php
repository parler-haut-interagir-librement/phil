<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Export;

use Override;
use Phil\DocumentImport\Internal\Export\Contract\Writer;
use Phil\DocumentImport\Internal\Mark;

/**
 * A WordprocessingML document written block after block: paragraphs and headings in the built-in styles Word knows,
 * lists through numbering definitions that restart with each list, tables with a repeated header row, inline
 * pictures with their text alternative, real footnotes, hyperlinks, page breaks and a table of contents field Word
 * fills when the document opens; then the package with its content types, relationships, styles and core properties.
 *
 * @internal
 */
final class DocxWriter implements Writer
{
    private const string NAMESPACES = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" '
        .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" '
        .'xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" '
        .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
        .'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"';

    private const string RELATIONSHIP = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/';

    /** The numbering definition of each kind of list: bullets, then each counter. */
    private const array ABSTRACTS = ['bullet' => 1, 'decimal' => 2, 'lower-alpha' => 3, 'upper-alpha' => 4, 'lower-roman' => 5, 'upper-roman' => 6];

    private const array FORMATS = ['bullet' => 'bullet', 'decimal' => 'decimal', 'lower-alpha' => 'lowerLetter', 'upper-alpha' => 'upperLetter', 'lower-roman' => 'lowerRoman', 'upper-roman' => 'upperRoman'];

    private const array ALIGNMENTS = ['center' => 'center', 'right' => 'right', 'justify' => 'both'];

    private string $body = '';

    /** @var list<string> */
    private array $relationships = [];

    /** @var array<string, string> the pictures, by their path in the package */
    private array $media = [];

    /** @var list<string> the numbering instances, each a list */
    private array $numbers = [];

    /** @var list<string> */
    private array $footnotes = [];

    /** @var array<string, int> */
    private array $written = [];

    private int $drawings = 0;

    private bool $fields = false;

    /**
     * @param array<string, list<Run>> $notes the footnotes of the document, by identifier
     */
    public function __construct(private readonly array $notes = [])
    {
    }

    #[Override]
    public function paragraph(array $runs, ?string $alignment = null, string $style = 'paragraph'): void
    {
        $styleId = ['quote' => 'Quote', 'caption' => 'Caption', 'title' => 'Title'][$style] ?? null;
        $this->body .= $this->p($runs, $styleId, $alignment);
    }

    #[Override]
    public function heading(int $level, array $runs, ?string $alignment = null): void
    {
        $this->body .= $this->p($runs, 'Heading'.$level, $alignment);
    }

    #[Override]
    public function list(array $items, bool $ordered, ?string $counter, ?int $start): void
    {
        $kind = $ordered ? (null !== $counter && isset(self::ABSTRACTS[$counter]) ? $counter : 'decimal') : 'bullet';
        $this->numbers[] = '<w:num w:numId="'.(\count($this->numbers) + 1).'"><w:abstractNumId w:val="'.self::ABSTRACTS[$kind].'"/>'
            .(null === $start || !$ordered ? '' : '<w:lvlOverride w:ilvl="0"><w:startOverride w:val="'.max(0, $start).'"/></w:lvlOverride>').'</w:num>';
        $this->items($items, \count($this->numbers), 0);
    }

    #[Override]
    public function code(string $code): void
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $code));
        $runs = '';

        foreach ($lines as $index => $line) {
            $runs .= (0 === $index ? '' : '<w:r><w:br/></w:r>').'<w:r><w:t xml:space="preserve">'.XmlText::escape($line).'</w:t></w:r>';
        }

        $this->body .= '<w:p><w:pPr><w:pStyle w:val="Code"/></w:pPr>'.$runs.'</w:p>';
    }

    #[Override]
    public function table(array $rows, bool $headings): void
    {
        if ([] === $rows) {
            return;
        }

        $width = max(array_map(\count(...), $rows));
        $xml = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="5000" w:type="pct"/><w:tblLook w:val="04A0" w:firstRow="'.($headings ? '1' : '0').'" w:lastRow="0" w:firstColumn="0" w:lastColumn="0" w:noHBand="0" w:noVBand="1"/></w:tblPr><w:tblGrid>'
            .str_repeat('<w:gridCol/>', $width).'</w:tblGrid>';

        foreach ($rows as $index => $row) {
            $header = $headings && 0 === $index;
            $xml .= '<w:tr>'.($header ? '<w:trPr><w:tblHeader/></w:trPr>' : '');

            foreach (array_pad($row, $width, []) as $cell) {
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="0" w:type="auto"/></w:tcPr>'.$this->p($cell, null, null).'</w:tc>';
            }

            $xml .= '</w:tr>';
        }

        $this->body .= $xml.'</w:tbl><w:p/>';
    }

    #[Override]
    public function image(Picture $picture): void
    {
        $number = ++$this->drawings;
        $path = 'media/image'.$number.'.'.$picture->extension();
        $this->media[$path] = $picture->bytes;
        $id = $this->relationship('image', $path);
        [$width, $height] = $picture->inches();
        $cx = (int) round($width * 914_400);
        $cy = (int) round($height * 914_400);
        $alt = XmlText::escape($picture->alt);

        $this->body .= '<w:p><w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0"><wp:extent cx="'.$cx.'" cy="'.$cy.'"/>'
            .'<wp:docPr id="'.$number.'" name="Picture '.$number.'" descr="'.$alt.'"/><wp:cNvGraphicFramePr><a:graphicFrameLocks noChangeAspect="1"/></wp:cNvGraphicFramePr>'
            .'<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic><pic:nvPicPr><pic:cNvPr id="'.$number.'" name="'.basename($path).'" descr="'.$alt.'"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="'.$id.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic></a:graphicData></a:graphic>'
            .'</wp:inline></w:drawing></w:r></w:p>';
    }

    #[Override]
    public function pageBreak(): void
    {
        $this->body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    #[Override]
    public function delimiter(): void
    {
        $this->body .= $this->p([new Run('* * *')], null, 'center');
    }

    #[Override]
    public function toc(string $title): void
    {
        $this->fields = true;

        $this->body .= '<w:sdt><w:sdtPr><w:docPartObj><w:docPartGallery w:val="Table of Contents"/><w:docPartUnique/></w:docPartObj></w:sdtPr><w:sdtContent>'
            .('' === trim($title) ? '' : $this->p(Runs::of($title), 'TOCHeading', null))
            .'<w:p><w:r><w:fldChar w:fldCharType="begin" w:dirty="true"/></w:r><w:r><w:instrText xml:space="preserve"> TOC \o "1-3" \h \z \u </w:instrText></w:r>'
            .'<w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r></w:p></w:sdtContent></w:sdt>';
    }

    #[Override]
    public function files(array $metadata): array
    {
        $this->relationship('styles', 'styles.xml');
        $this->relationship('numbering', 'numbering.xml');
        $this->relationship('footnotes', 'footnotes.xml');
        $this->relationship('settings', 'settings.xml');
        $defaults = '';

        foreach (array_unique(array_map(static fn (string $path): string => pathinfo($path, \PATHINFO_EXTENSION), array_keys($this->media))) as $extension) {
            $defaults .= '<Default Extension="'.$extension.'" ContentType="image/'.$extension.'"/>';
        }

        $files = [
            '[Content_Types].xml' => XmlText::DECLARATION.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'.$defaults
                .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
                .'<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>'
                .'<Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>'
                .'<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
                .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>',
            '_rels/.rels' => XmlText::DECLARATION.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="'.self::RELATIONSHIP.'officeDocument" Target="word/document.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>',
            'docProps/core.xml' => $this->core($metadata),
            'word/document.xml' => XmlText::DECLARATION.'<w:document '.self::NAMESPACES.'><w:body>'.$this->body
                .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>',
            'word/_rels/document.xml.rels' => XmlText::DECLARATION.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.implode('', $this->relationships).'</Relationships>',
            'word/styles.xml' => DocxStylesheet::STYLES,
            'word/numbering.xml' => XmlText::DECLARATION.'<w:numbering '.self::NAMESPACES.'>'.$this->abstracts().implode('', $this->numbers).'</w:numbering>',
            'word/footnotes.xml' => XmlText::DECLARATION.'<w:footnotes '.self::NAMESPACES.'><w:footnote w:type="separator" w:id="0"><w:p><w:r><w:separator/></w:r></w:p></w:footnote>'
                .'<w:footnote w:type="continuationSeparator" w:id="1"><w:p><w:r><w:continuationSeparator/></w:r></w:p></w:footnote>'.implode('', $this->footnotes).'</w:footnotes>',
            'word/settings.xml' => XmlText::DECLARATION.'<w:settings '.self::NAMESPACES.'>'.($this->fields ? '<w:updateFields w:val="true"/>' : '').'<w:footnotePr><w:footnote w:id="0"/><w:footnote w:id="1"/></w:footnotePr></w:settings>',
        ];

        foreach ($this->media as $path => $bytes) {
            $files['word/'.$path] = $bytes;
        }

        return $files;
    }

    /**
     * @param list<ListItem> $items
     */
    private function items(array $items, int $number, int $level): void
    {
        foreach ($items as $item) {
            $runs = null === $item->checked ? $item->runs : [new Run($item->checked ? '☑ ' : '☐ '), ...$item->runs];
            $this->body .= $this->p($runs, 'ListParagraph', null, '<w:numPr><w:ilvl w:val="'.$level.'"/><w:numId w:val="'.$number.'"/></w:numPr>');
            $this->items($item->items, $number, $level + 1);
        }
    }

    /**
     * @param list<Run> $runs
     */
    private function p(array $runs, ?string $style, ?string $alignment, string $numbering = ''): string
    {
        $properties = (null === $style ? '' : '<w:pStyle w:val="'.$style.'"/>').$numbering.(isset(self::ALIGNMENTS[$alignment ?? '']) ? '<w:jc w:val="'.self::ALIGNMENTS[$alignment].'"/>' : '');

        return '<w:p>'.('' === $properties ? '' : '<w:pPr>'.$properties.'</w:pPr>').$this->runs($runs, true).'</w:p>';
    }

    /**
     * @param list<Run> $runs
     */
    private function runs(array $runs, bool $links): string
    {
        $xml = '';

        foreach ($runs as $run) {
            $xml .= match (true) {
                null !== $run->note => $this->reference($run),
                $run->break => '<w:r><w:br/></w:r>',
                null !== $run->link && $links => '<w:hyperlink r:id="'.$this->relationship('hyperlink', $run->link, true).'">'.$this->run($run->text, $run->marks, 'Hyperlink').'</w:hyperlink>',
                null !== $run->link => $this->run($run->text.' ('.$run->link.')', $run->marks),
                default => $this->run($run->text, $run->marks),
            };
        }

        return $xml;
    }

    /**
     * The reference to a footnote, the footnote written at its first reference; a reference to a footnote the
     * document does not hold stays its number in exponent.
     */
    private function reference(Run $run): string
    {
        $id = $run->note ?? '';

        if (!isset($this->notes[$id])) {
            return $this->run($run->text, $run->marks);
        }

        if (!isset($this->written[$id])) {
            $this->written[$id] = \count($this->footnotes) + 2;
            $this->footnotes[] = '<w:footnote w:id="'.$this->written[$id].'"><w:p><w:pPr><w:pStyle w:val="FootnoteText"/></w:pPr><w:r><w:rPr><w:rStyle w:val="FootnoteReference"/></w:rPr><w:footnoteRef/></w:r>'
                .'<w:r><w:t xml:space="preserve"> </w:t></w:r>'.$this->runs($this->notes[$id], false).'</w:p></w:footnote>';
        }

        return '<w:r><w:rPr><w:rStyle w:val="FootnoteReference"/></w:rPr><w:footnoteReference w:id="'.$this->written[$id].'"/></w:r>';
    }

    /**
     * @param list<Mark> $marks
     */
    private function run(string $text, array $marks, ?string $style = null): string
    {
        $has = static fn (Mark $mark): bool => \in_array($mark, $marks, true);
        $style ??= $has(Mark::Code) ? 'CodeChar' : null;
        $properties = (null === $style ? '' : '<w:rStyle w:val="'.$style.'"/>')
            .($has(Mark::Bold) ? '<w:b/>' : '')
            .($has(Mark::Italic) ? '<w:i/>' : '')
            .($has(Mark::Strikethrough) ? '<w:strike/>' : '')
            .($has(Mark::Marker) ? '<w:highlight w:val="yellow"/>' : '')
            .($has(Mark::Underline) ? '<w:u w:val="single"/>' : '')
            .($has(Mark::Superscript) ? '<w:vertAlign w:val="superscript"/>' : ($has(Mark::Subscript) ? '<w:vertAlign w:val="subscript"/>' : ''));

        return '<w:r>'.('' === $properties ? '' : '<w:rPr>'.$properties.'</w:rPr>').'<w:t xml:space="preserve">'.XmlText::escape($text).'</w:t></w:r>';
    }

    private function relationship(string $type, string $target, bool $external = false): string
    {
        $id = 'rId'.(\count($this->relationships) + 1);
        $this->relationships[] = '<Relationship Id="'.$id.'" Type="'.self::RELATIONSHIP.$type.'" Target="'.XmlText::escape($target).'"'.($external ? ' TargetMode="External"' : '').'/>';

        return $id;
    }

    /**
     * The numbering definitions: bullets, then decimal, letters and roman numerals, nine levels each.
     */
    private function abstracts(): string
    {
        $xml = '';
        $bullets = ['•', '◦', '▪'];

        foreach (self::ABSTRACTS as $kind => $id) {
            $xml .= '<w:abstractNum w:abstractNumId="'.$id.'"><w:multiLevelType w:val="hybridMultilevel"/>';

            for ($level = 0; $level < 9; ++$level) {
                $format = 'bullet' === $kind ? 'bullet' : self::FORMATS[$kind];
                $text = 'bullet' === $kind ? $bullets[$level % 3] : '%'.($level + 1).'.';
                $xml .= '<w:lvl w:ilvl="'.$level.'"><w:start w:val="1"/><w:numFmt w:val="'.$format.'"/><w:lvlText w:val="'.$text.'"/><w:lvlJc w:val="left"/>'
                    .'<w:pPr><w:ind w:left="'.(720 * ($level + 1)).'" w:hanging="360"/></w:pPr></w:lvl>';
            }

            $xml .= '</w:abstractNum>';
        }

        return $xml;
    }

    /**
     * @param array<string, string> $metadata
     */
    private function core(array $metadata): string
    {
        $elements = ['title' => 'dc:title', 'subject' => 'dc:subject', 'creator' => 'dc:creator', 'keywords' => 'cp:keywords', 'description' => 'dc:description', 'language' => 'dc:language'];
        $xml = '';

        foreach ($elements as $key => $element) {
            if (isset($metadata[$key]) && '' !== trim($metadata[$key])) {
                $xml .= '<'.$element.'>'.XmlText::escape($metadata[$key]).'</'.$element.'>';
            }
        }

        foreach (['created' => 'dcterms:created', 'modified' => 'dcterms:modified'] as $key => $element) {
            if (isset($metadata[$key]) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?(Z|[+-]\d{2}:\d{2})?)?$/D', $metadata[$key])) {
                $xml .= '<'.$element.' xsi:type="dcterms:W3CDTF">'.$metadata[$key].'</'.$element.'>';
            }
        }

        return XmlText::DECLARATION.'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" '
            .'xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'.$xml.'</cp:coreProperties>';
    }
}
