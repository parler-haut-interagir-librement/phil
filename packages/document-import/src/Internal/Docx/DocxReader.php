<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Docx;

use Dom\Element;
use Dom\XMLDocument;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Internal\Archive;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Line;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Internal\Xml;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\StyleRole;

/**
 * A WordprocessingML document read into blocks: the main part found through the package relationships, its body
 * walked paragraph by paragraph and table by table, with the styles, the numbering, the notes, the hyperlinks and the
 * images it refers to. A strict Office Open XML document is read the same way, its namespaces read as their
 * transitional forms.
 *
 * @internal
 */
final class DocxReader
{
    private const string W = DocxStyles::W;

    private const string R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const string PACKAGE = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const string MC = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    private const string MATH = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    private const string DRAWING = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const string PLACEMENT = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';

    private const string CHART = 'http://schemas.openxmlformats.org/drawingml/2006/chart';

    private const string VML = 'urn:schemas-microsoft-com:vml';

    private const array ALIGNMENTS = ['center' => 'center', 'right' => 'right', 'end' => 'right', 'both' => 'justify', 'distribute' => 'justify'];

    /** The namespaces of strict Office Open XML, read as their transitional forms. */
    private const array STRICT = [
        'http://purl.oclc.org/ooxml/wordprocessingml/main' => self::W,
        'http://purl.oclc.org/ooxml/officeDocument/relationships' => self::R,
        'http://purl.oclc.org/ooxml/officeDocument/math' => self::MATH,
        'http://purl.oclc.org/ooxml/drawingml/main' => self::DRAWING,
        'http://purl.oclc.org/ooxml/drawingml/wordprocessingDrawing' => self::PLACEMENT,
        'http://purl.oclc.org/ooxml/drawingml/chart' => self::CHART,
        'http://purl.oclc.org/ooxml/drawingml/picture' => 'http://schemas.openxmlformats.org/drawingml/2006/picture',
    ];

    private const array METADATA = [
        'title' => 'title',
        'subject' => 'subject',
        'creator' => 'creator',
        'keywords' => 'keywords',
        'description' => 'description',
        'language' => 'language',
        'created' => 'created',
        'modified' => 'modified',
    ];

    /** @var array<string, array{string, string, bool}> */
    private array $relationships = [];

    private DocxStyles $styles;

    /** @var array<string, array<string, Element>> */
    private array $notes = [];

    /** @var list<array{bool, string, bool}> */
    private array $fields = [];

    private int $skipped = 0;

    public function __construct(
        private readonly Archive $archive,
        private readonly Builder $out,
    ) {
        $this->styles = new DocxStyles(null, null);
    }

    /**
     * The document read into the builder, and its core properties as metadata.
     *
     * @return array<string, string>
     *
     * @throws InvalidDocument
     */
    public function read(): array
    {
        $package = $this->relationships('_rels/.rels');
        $main = $this->target($package, 'officeDocument') ?? throw InvalidDocument::because('The archive has no main document part: it is not a word processing document.');
        $body = Xml::child($this->xml($main)?->documentElement, self::W, 'body') ?? throw InvalidDocument::because(\sprintf('The part "%s" has no document body.', $main));
        $this->relationships = $this->relationships(Archive::resolve(\dirname($main), '_rels/'.basename($main).'.rels'));
        $this->styles = new DocxStyles($this->part('styles'), $this->part('numbering'), $this->out->styles);

        foreach (['footnotes' => 'footnote', 'endnotes' => 'endnote'] as $type => $name) {
            foreach (Xml::children($this->part($type)?->documentElement, self::W, $name) as $note) {
                $this->notes[$type][$note->getAttributeNS(self::W, 'id') ?? ''] = $note;
            }
        }

        foreach ($this->relationships as [$type, $target]) {
            if (\in_array($type, ['header', 'footer'], true) && '' !== trim($this->xml($target)->documentElement->textContent ?? '')) {
                $this->out->report->add(Omitted::HeaderFooter, $type);
            }

            if ('vbaProject' === $type) {
                $this->out->report->add(Omitted::Macro);
            }
        }

        $this->blocks($body);
        $core = $this->target($package, 'core-properties');

        return Xml::metadata(null === $core ? null : $this->xml($core)?->documentElement, self::METADATA);
    }

    /**
     * The relationships of a part: by identifier, their type — the last segment of its URI —, their target, resolved
     * from the folder of the part unless it is external, and whether it is.
     *
     * @return array<string, array{string, string, bool}>
     */
    private function relationships(string $path): array
    {
        $folder = \dirname($path, 2);
        $relationships = [];

        foreach (Xml::children($this->xml($path)?->documentElement, self::PACKAGE, 'Relationship') as $relationship) {
            $type = $relationship->getAttribute('Type') ?? '';
            $target = $relationship->getAttribute('Target') ?? '';
            $external = 'External' === $relationship->getAttribute('TargetMode');
            $relationships[$relationship->getAttribute('Id') ?? ''] = [basename($type), $external ? $target : Archive::resolve($folder, $target), $external];
        }

        return $relationships;
    }

    /**
     * @param array<string, array{string, string, bool}> $relationships
     */
    private function target(array $relationships, string $type): ?string
    {
        foreach ($relationships as [$kind, $target, $external]) {
            if ($type === $kind && !$external) {
                return $target;
            }
        }

        return null;
    }

    private function part(string $type): ?XMLDocument
    {
        $target = $this->target($this->relationships, $type);

        return null === $target ? null : $this->xml($target);
    }

    private function blocks(Element $container): void
    {
        foreach (Xml::children($container, self::W) as $node) {
            match ($node->localName) {
                'p' => $this->paragraph($node),
                'tbl' => $this->table($node),
                'sdt' => $this->contentControl($node),
                'sdtContent', 'customXml', 'ins' => $this->blocks($node),
                'del' => $this->out->report->add(Omitted::TrackedChange, 'deletion'),
                default => null,
            };
        }
    }

    /**
     * A content control: the table of contents Word generates becomes a `toc` block titled by its heading, any other
     * control its content.
     */
    private function contentControl(Element $control): void
    {
        $gallery = DocxStyles::value(Xml::descendant(Xml::child($control, self::W, 'sdtPr'), self::W, 'docPartGallery')) ?? '';

        if (str_contains($gallery, 'Table of Contents')) {
            $title = '';

            foreach (Xml::children(Xml::child($control, self::W, 'sdtContent'), self::W, 'p') as $paragraph) {
                if ('' === $title && $this->styles->isTocHeading(DocxStyles::value(Xml::child(Xml::child($paragraph, self::W, 'pPr'), self::W, 'pStyle')) ?? '')) {
                    $title = trim(strip_tags($this->inlineOf($paragraph)));
                }
            }

            $this->out->toc(htmlspecialchars_decode($title, \ENT_QUOTES));

            return;
        }

        $this->blocks($control);
    }

    private function paragraph(Element $paragraph): void
    {
        $properties = Xml::child($paragraph, self::W, 'pPr');
        $style = DocxStyles::value(Xml::child($properties, self::W, 'pStyle')) ?? '';
        $role = $this->styles->role($style);
        $alignment = self::ALIGNMENTS[DocxStyles::value(Xml::child($properties, self::W, 'jc')) ?? ''] ?? null;
        $numbering = $this->styles->mapped($style) instanceof StyleRole ? null : DocxStyles::numberingOf(Xml::child($properties, self::W, 'numPr')) ?? $this->styles->numbering($style);
        $list = null === $numbering ? null : $this->styles->list($numbering[0]);
        $name = $this->styles->name($style);

        $line = new Line(function (string $html) use ($role, $alignment, $numbering, $list, $name): void {
            if (null !== $numbering && null !== $list) {
                $this->out->listItem($numbering[0], $numbering[1], $html, ...$list);

                return;
            }

            $this->out->block($role, $html, $alignment, $name);
        });

        $this->inline($paragraph, $line, null);
        $line->flush();
    }

    private function inline(?Element $parent, Line $line, ?string $link): void
    {
        foreach ($parent->children ?? [] as $node) {
            match ([$node->namespaceURI, $node->localName]) {
                [self::W, 'r'] => $this->run($node, $line, $link),
                [self::W, 'hyperlink'] => $this->inline($node, $line, $this->hyperlink($node) ?? $link),
                [self::W, 'fldSimple'] => $this->inline($node, $line, DocxStyles::fieldLink($node->getAttributeNS(self::W, 'instr') ?? '') ?? $link),
                [self::W, 'ins'], [self::W, 'smartTag'], [self::W, 'customXml'], [self::W, 'sdt'], [self::W, 'sdtContent'], [self::W, 'bdo'], [self::W, 'dir'] => $this->inline($node, $line, $link),
                [self::W, 'del'], [self::W, 'moveFrom'] => $this->out->report->add(Omitted::TrackedChange, 'deletion'),
                [self::MATH, 'oMath'], [self::MATH, 'oMathPara'] => $this->out->report->add(Omitted::Equation),
                [self::MC, 'AlternateContent'] => $this->inline($this->choice($node), $line, $link),
                default => null,
            };
        }
    }

    /**
     * The target of a hyperlink: its external relationship; a link to a bookmark of the document is reported and
     * keeps its text only.
     */
    private function hyperlink(Element $hyperlink): ?string
    {
        if (null !== $hyperlink->getAttributeNS(self::W, 'anchor')) {
            $this->out->report->add(Omitted::InternalLink);
        }

        $relationship = $this->relationships[$hyperlink->getAttributeNS(self::R, 'id') ?? ''] ?? null;

        return null !== $relationship && $relationship[2] ? $relationship[1] : null;
    }

    /**
     * The first choice of a markup compatibility alternative, which Word writes for the current applications.
     */
    private function choice(Element $alternative): ?Element
    {
        return Xml::child($alternative, self::MC, 'Choice');
    }

    private function run(Element $run, Line $line, ?string $link): void
    {
        $properties = Xml::child($run, self::W, 'rPr');

        if (DocxStyles::on(Xml::child($properties, self::W, 'vanish'))) {
            $this->out->report->add(Omitted::HiddenText);

            return;
        }

        foreach (['color' => 'colour', 'rFonts' => 'font', 'sz' => 'size'] as $name => $detail) {
            $property = Xml::child($properties, self::W, $name);

            if ($property instanceof Element && 'auto' !== DocxStyles::value($property)) {
                $this->out->report->add(Omitted::Formatting, $detail);
            }
        }

        $marks = [...DocxStyles::runMarks($properties), ...$this->styles->marks(DocxStyles::value(Xml::child($properties, self::W, 'rStyle')) ?? '')];

        $this->runContents($run, $line, Mark::tags($marks, $link ?? $this->fieldLinkInResult()));
    }

    /**
     * @param list<string> $tags
     */
    private function runContents(?Element $run, Line $line, array $tags): void
    {
        foreach ($run->children ?? [] as $node) {
            $this->runContent($node, $line, $tags);
        }
    }

    /**
     * @param list<string> $tags
     */
    private function runContent(Element $node, Line $line, array $tags): void
    {
        match ([$node->namespaceURI, $node->localName]) {
            [self::W, 't'] => $this->runText($line, $node->textContent ?? '', $tags),
            [self::W, 'tab'] => $this->runText($line, ' ', $tags),
            [self::W, 'noBreakHyphen'] => $this->runText($line, "\u{2011}", $tags),
            [self::W, 'softHyphen'] => $this->runText($line, "\u{AD}", $tags),
            [self::W, 'br'] => 'page' === $node->getAttributeNS(self::W, 'type') ? $this->pageBreak($line) : $line->inline->lineBreak($tags),
            [self::W, 'cr'] => $line->inline->lineBreak($tags),
            [self::W, 'sym'] => $this->out->report->add(Omitted::Formatting, 'symbol'),
            [self::W, 'drawing'] => $this->drawing($node, $line),
            [self::W, 'pict'] => $this->picture($node, $line),
            [self::W, 'object'] => $this->out->report->add(Omitted::EmbeddedObject),
            [self::W, 'footnoteReference'] => $this->note($node, 'footnotes', $line),
            [self::W, 'endnoteReference'] => $this->note($node, 'endnotes', $line),
            [self::W, 'commentReference'] => $this->out->report->add(Omitted::Comment),
            [self::W, 'fldChar'] => $this->fieldCharacter($node->getAttributeNS(self::W, 'fldCharType') ?? '', $line),
            [self::W, 'instrText'] => $this->instruction($node->textContent ?? ''),
            [self::MC, 'AlternateContent'] => $this->runContents($this->choice($node), $line, $tags),
            default => null,
        };
    }

    /**
     * Text of a run, unless it is the instruction of a field or the result of a table of contents.
     *
     * @param list<string> $tags
     */
    private function runText(Line $line, string $text, array $tags): void
    {
        if (!$this->inInstruction() && 0 === $this->skipped) {
            $line->inline->text($text, $tags);
        }
    }

    private function pageBreak(Line $line): void
    {
        if ($line->holdsBlocks()) {
            $line->flush();
            $this->out->pageBreak();
        }
    }

    /**
     * A DrawingML object: a picture becomes an image block; a chart, a text box or a shape is reported.
     */
    private function drawing(Element $drawing, Line $line): void
    {
        $picture = Xml::descendant($drawing, self::DRAWING, 'blip');
        $properties = Xml::descendant($drawing, self::PLACEMENT, 'docPr');

        if ($picture instanceof Element) {
            $description = $properties?->getAttribute('descr') ?? '';
            $this->image($picture->getAttributeNS(self::R, 'embed') ?? '', '' === $description ? $properties?->getAttribute('title') ?? '' : $description, $line);

            return;
        }

        $this->shape($drawing, Xml::descendant($drawing, self::CHART, 'chart') instanceof Element ? 'chart' : 'shape');
    }

    /**
     * A VML picture, as older documents and the fallback of new ones hold them.
     */
    private function picture(Element $picture, Line $line): void
    {
        $data = Xml::descendant($picture, self::VML, 'imagedata');

        if (!$data instanceof Element) {
            $this->shape($picture, 'shape');

            return;
        }

        $this->image($data->getAttributeNS(self::R, 'id') ?? '', Xml::descendant($picture, self::VML, 'shape')?->getAttribute('alt') ?? '', $line);
    }

    private function shape(Element $shape, string $kind): void
    {
        if (!Xml::descendant($shape, self::W, 'txbxContent') instanceof Element) {
            $this->out->report->add(Omitted::Drawing, $kind);

            return;
        }

        $this->out->report->add(Omitted::TextBox);
    }

    /**
     * An image: an image block where the paragraph stands, or after the table, the list or the paragraph of the note
     * that holds it.
     */
    private function image(string $id, string $description, Line $line): void
    {
        [, $target, $external] = $this->relationships[$id] ?? ['', '', false];
        $bytes = $this->archive->get($target);
        $blocks = $line->holdsBlocks();

        if ($blocks) {
            $line->flush();
        }

        match (true) {
            $external && 1 === preg_match('~^https?://~i', $target) => $this->out->linkedImage($target, $description, !$blocks),
            null !== $bytes && !$external => $this->out->image(new Media($target, $bytes, $description), !$blocks),
            default => $this->out->report->add(Omitted::Image, $external ? 'linked file' : 'missing'),
        };
    }

    private function note(Element $reference, string $type, Line $line): void
    {
        $note = $this->notes[$type][$reference->getAttributeNS(self::W, 'id') ?? ''] ?? null;

        if (null !== $note) {
            $line->inline->html($this->out->note($this->text($note), 'endnotes' === $type));
        }
    }

    /**
     * The text of a table cell or a note: its paragraphs as inline HTML joined by line breaks, the cells of a table
     * inside it by spaces.
     */
    private function text(Element $container): string
    {
        $lines = [];

        foreach (Xml::children($container, self::W) as $node) {
            $html = match ($node->localName) {
                'p' => $this->inlineOf($node),
                'tbl' => $this->nestedTable($node),
                'sdt', 'sdtContent', 'customXml' => $this->text($node),
                default => '',
            };

            if ('' !== $html) {
                $lines[] = $html;
            }
        }

        return implode('<br>', $lines);
    }

    private function inlineOf(Element $paragraph): string
    {
        $line = new Line();
        $this->inline($paragraph, $line, null);

        return $line->inline->toHtml();
    }

    private function nestedTable(Element $table): string
    {
        $this->out->report->add(Omitted::NestedTable);
        $cells = [];

        foreach (Xml::children($table, self::W, 'tr') as $row) {
            foreach (Xml::children($row, self::W, 'tc') as $cell) {
                $cells[] = $this->text($cell);
            }
        }

        return trim(implode(' ', $cells));
    }

    private function table(Element $table): void
    {
        $rows = [];
        $headings = false;

        foreach (Xml::children($table, self::W, 'tr') as $row) {
            $headings = $headings || ([] === $rows && DocxStyles::on(Xml::child(Xml::child($row, self::W, 'trPr'), self::W, 'tblHeader')));
            $cells = [];

            foreach (Xml::children($row, self::W, 'tc') as $cell) {
                $properties = Xml::child($cell, self::W, 'tcPr');
                $merge = Xml::child($properties, self::W, 'vMerge');
                $continued = $merge instanceof Element && 'restart' !== DocxStyles::value($merge);
                $cells = [...$cells, ...$this->spanned($continued ? '' : $this->text($cell), (int) DocxStyles::value(Xml::child($properties, self::W, 'gridSpan')), $continued)];
            }

            $rows[] = $cells;
        }

        if ([] !== $rows) {
            $this->out->table($rows, $headings);
        }
    }

    /**
     * A cell followed by the empty cells of the columns it spans; a cell spanning columns or continuing a cell of
     * the row above is reported.
     *
     * @return list<string>
     */
    private function spanned(string $content, int $span, bool $continued): array
    {
        if ($continued || $span > 1) {
            $this->out->report->add(Omitted::MergedCell);
        }

        return array_pad([$content], $span, '');
    }

    private function inInstruction(): bool
    {
        $field = array_last($this->fields);

        return null !== $field && !$field[0];
    }

    private function instruction(string $text): void
    {
        $last = array_key_last($this->fields);

        if (null !== $last) {
            $this->fields[$last][1] .= $text;
        }
    }

    /**
     * The fields of the text, which may span paragraphs: their instructions are not text, their results are; a
     * `HYPERLINK` field links its result, and a `TOC` field becomes a `toc` block, its result left out.
     */
    private function fieldCharacter(string $type, Line $line): void
    {
        $last = array_key_last($this->fields);

        if ('begin' === $type) {
            $this->fields[] = [false, '', false];
        } elseif (null !== $last && 'separate' === $type) {
            $toc = 1 === preg_match('/^\s*TOC\b/', $this->fields[$last][1]);
            $this->fields[$last] = [true, $this->fields[$last][1], $toc];

            if ($toc) {
                $line->flush();
                $this->out->toc('');
                ++$this->skipped;
            }
        } elseif (null !== $last && 'end' === $type) {
            $this->skipped -= array_pop($this->fields)[2] ? 1 : 0;
        }
    }

    private function fieldLinkInResult(): ?string
    {
        foreach (array_reverse($this->fields) as [$result, $instruction]) {
            $link = $result ? DocxStyles::fieldLink($instruction) : null;

            if (null !== $link) {
                return $link;
            }
        }

        return null;
    }

    /**
     * @throws InvalidDocument
     */
    private function xml(string $name): ?XMLDocument
    {
        return $this->archive->xml($name, self::STRICT);
    }
}
