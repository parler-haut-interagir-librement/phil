<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Odt;

use Dom\Element;
use Dom\Text;
use Phil\DocumentImport\Exception\InvalidDocument;
use Phil\DocumentImport\Internal\Archive;
use Phil\DocumentImport\Internal\Base64;
use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Line;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Internal\Xml;
use Phil\DocumentImport\Media;
use Phil\DocumentImport\Omitted;
use Phil\DocumentImport\StyleRole;

/**
 * An OpenDocument text read into blocks: the text of `content.xml` walked paragraph by paragraph, list by list and
 * table by table, with the styles of `content.xml` and `styles.xml`, the notes, the links and the pictures it holds;
 * or the same parts of a flat OpenDocument text, held by its one `office:document`.
 *
 * @internal
 */
final class OdtReader
{
    private const string OFFICE = OdtStyles::OFFICE;

    private const string STYLE = OdtStyles::STYLE;

    private const string TEXT = OdtStyles::TEXT;

    private const string TABLE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

    private const string DRAW = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';

    private const string SVG = 'urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0';

    private const string XLINK = 'http://www.w3.org/1999/xlink';

    /** The most times a repeated row or cell is written, against a table that claims millions. */
    private const int REPEAT = 256;

    private const array METADATA = [
        'title' => 'title',
        'subject' => 'subject',
        'initial-creator' => 'creator',
        'keyword' => 'keywords',
        'description' => 'description',
        'language' => 'language',
        'creation-date' => 'created',
        'date' => 'modified',
    ];

    private OdtStyles $styles;

    public function __construct(
        private readonly Archive $archive,
        private readonly Builder $out,
    ) {
        $this->styles = new OdtStyles();
    }

    /**
     * The document read into the builder, and its metadata.
     *
     * @return array<string, string>
     *
     * @throws InvalidDocument
     */
    public function read(): array
    {
        $content = $this->archive->xml('content.xml')?->documentElement;
        $styles = $this->archive->xml('styles.xml')?->documentElement;
        $macros = array_any($this->archive->names(), static fn (string $name): bool => str_starts_with($name, 'Basic/') || str_starts_with($name, 'Scripts/'));

        return $this->parts($content, $styles, $this->archive->xml('meta.xml')?->documentElement, 'The archive has no text in "content.xml": it is not an OpenDocument text.', $macros);
    }

    /**
     * A flat OpenDocument text read into the builder, and its metadata: one `office:document` that holds the
     * metadata, the styles, the scripts and the body, its images embedded in Base64.
     *
     * @return array<string, string>
     *
     * @throws InvalidDocument
     */
    public function readFlat(): array
    {
        $document = $this->archive->xml('content.xml')?->documentElement;

        if (self::OFFICE !== $document?->namespaceURI || 'document' !== $document->localName || 'application/vnd.oasis.opendocument.text' !== $document->getAttributeNS(self::OFFICE, 'mimetype')) {
            throw InvalidDocument::because('The file is not a flat OpenDocument text: its root is not an office:document of a text.');
        }

        $macros = [] !== Xml::children(Xml::child($document, self::OFFICE, 'scripts'), self::OFFICE);

        return $this->parts($document, $document, $document, 'The flat document has no office:text in its body.', $macros);
    }

    /**
     * @return array<string, string>
     *
     * @throws InvalidDocument
     */
    private function parts(?Element $content, ?Element $styles, ?Element $meta, string $missing, bool $macros): array
    {
        $text = Xml::child(Xml::child($content, self::OFFICE, 'body'), self::OFFICE, 'text') ?? throw InvalidDocument::because($missing);
        $this->styles = new OdtStyles($this->out->styles, $styles, $content);

        foreach (['header', 'footer'] as $kind) {
            foreach ($styles?->getElementsByTagNameNS(self::STYLE, $kind) ?? [] as $part) {
                if ('' !== trim($part->textContent ?? '')) {
                    $this->out->report->add(Omitted::HeaderFooter, $kind);
                }
            }
        }

        if ($macros) {
            $this->out->report->add(Omitted::Macro);
        }

        $this->blocks($text);

        return Xml::metadata(Xml::child($meta, self::OFFICE, 'meta'), self::METADATA);
    }

    private function blocks(Element $container): void
    {
        foreach ($container->children as $node) {
            match ([$node->namespaceURI, $node->localName]) {
                [self::TEXT, 'p'] => $this->paragraph($node, null),
                [self::TEXT, 'h'] => $this->paragraph($node, Role::heading((int) ($node->getAttributeNS(self::TEXT, 'outline-level') ?? '1'))),
                [self::TEXT, 'list'] => $this->list($node, 0, '', $node->getNodePath()),
                [self::TABLE, 'table'] => $this->table($node),
                [self::TEXT, 'section'] => $this->blocks($node),
                [self::TEXT, 'table-of-content'] => $this->out->toc(trim(Xml::descendant($node, self::TEXT, 'index-title')->textContent ?? '')),
                [self::TEXT, 'tracked-changes'] => $this->trackedChanges($node),
                [self::DRAW, 'frame'] => $this->anchoredToPage($node),
                default => null,
            };
        }
    }

    private function trackedChanges(Element $changes): void
    {
        foreach ($changes->getElementsByTagNameNS(self::TEXT, 'deletion') as $deletion) {
            $this->out->report->add(Omitted::TrackedChange, 'deletion');
        }
    }

    private function anchoredToPage(Element $frame): void
    {
        $this->frame($frame, new Line($this->out->paragraph(...)));
    }

    private function paragraph(Element $paragraph, ?Role $heading): void
    {
        $style = $paragraph->getAttributeNS(self::TEXT, 'style-name') ?? '';

        if ($this->styles->breaksPage($style)) {
            $this->out->pageBreak();
        }

        $mapped = $this->styles->mapped($style);
        $role = $mapped instanceof StyleRole ? Role::mapped($mapped) : $heading ?? $this->styles->role($style);
        $alignment = $this->styles->alignment($style);
        $name = $this->styles->name($style);
        $line = new Line(fn (string $html) => $this->out->block($role, $html, $alignment, $name));
        $this->inline($paragraph, $line, $this->styles->directMarks('paragraph', $style), null);
        $line->flush();
    }

    /**
     * @param list<Mark> $marks
     */
    private function inline(Element $parent, Line $line, array $marks, ?string $link): void
    {
        foreach ($parent->childNodes as $node) {
            if ($node instanceof Text) {
                $line->inline->text(strtr($node->data, "\t\r\n", '   '), Mark::tags($marks, $link));
            }

            if ($node instanceof Element) {
                $this->node($node, $line, $marks, $link);
            }
        }
    }

    /**
     * @param list<Mark> $marks
     */
    private function node(Element $node, Line $line, array $marks, ?string $link): void
    {
        $tags = Mark::tags($marks, $link);

        match ([$node->namespaceURI, $node->localName]) {
            [self::TEXT, 'span'] => $this->span($node, $line, $marks, $link),
            [self::TEXT, 'a'] => $this->inline($node, $line, $marks, $this->link($node) ?? $link),
            [self::TEXT, 's'] => $line->inline->text(str_repeat(' ', max(1, (int) $node->getAttributeNS(self::TEXT, 'c'))), $tags),
            [self::TEXT, 'tab'] => $line->inline->text(' ', $tags),
            [self::TEXT, 'line-break'] => $line->inline->lineBreak($tags),
            [self::TEXT, 'note'] => $this->note($node, $line),
            [self::OFFICE, 'annotation'] => $this->out->report->add(Omitted::Comment),
            [self::DRAW, 'frame'] => $this->frame($node, $line),
            [self::DRAW, 'a'] => $this->inline($node, $line, $marks, $link),
            default => match ($node->namespaceURI) {
                self::TEXT => $this->inline($node, $line, $marks, $link),
                self::DRAW => $this->out->report->add(Omitted::Drawing, 'shape'),
                default => null,
            },
        };
    }

    /**
     * @param list<Mark> $marks
     */
    private function span(Element $span, Line $line, array $marks, ?string $link): void
    {
        $style = $span->getAttributeNS(self::TEXT, 'style-name') ?? '';

        if ($this->styles->isHidden('text', $style)) {
            $this->out->report->add(Omitted::HiddenText);

            return;
        }

        foreach ($this->styles->leftOut('text', $style) as $detail) {
            $this->out->report->add(Omitted::Formatting, $detail);
        }

        $this->inline($span, $line, [...$marks, ...$this->styles->marks('text', $style)], $link);
    }

    /**
     * The target of a link; a link to a place of the document is reported and keeps its text only.
     */
    private function link(Element $link): ?string
    {
        $href = $link->getAttributeNS(self::XLINK, 'href') ?? '';

        if (str_starts_with($href, '#')) {
            $this->out->report->add(Omitted::InternalLink);

            return null;
        }

        return $href;
    }

    /**
     * A frame: a picture becomes an image block; a text box, an embedded object or a chart is reported.
     */
    private function frame(Element $frame, Line $line): void
    {
        $content = null;

        foreach ($frame->children as $child) {
            $content ??= self::DRAW === $child->namespaceURI ? $child : null;
        }

        $description = trim(Xml::child($frame, self::SVG, 'desc')->textContent ?? '');

        match ($content?->localName) {
            'image' => $this->image($content, '' === $description ? trim(Xml::child($frame, self::SVG, 'title')->textContent ?? '') : $description, $line),
            'text-box' => $this->out->report->add(Omitted::TextBox),
            'object', 'object-ole' => $this->out->report->add(Omitted::EmbeddedObject),
            default => $this->out->report->add(Omitted::Drawing, 'shape'),
        };
    }

    /**
     * An image: an image block where the paragraph stands, or after the table, the list or the paragraph of the note
     * that holds it.
     */
    private function image(Element $image, string $description, Line $line): void
    {
        $href = $image->getAttributeNS(self::XLINK, 'href') ?? '';
        $binary = Xml::child($image, self::OFFICE, 'binary-data');
        $path = Archive::resolve('.', $href);
        $bytes = $binary instanceof Element ? Base64::decode($binary->textContent ?? '') : $this->archive->get($path);
        $blocks = $line->holdsBlocks();

        if ($blocks) {
            $line->flush();
        }

        match (true) {
            1 === preg_match('~^https?://~i', $href) => $this->out->linkedImage($href, $description, !$blocks),
            \is_string($bytes) => $this->out->image(new Media('' === $path ? 'image' : $path, $bytes, $description), !$blocks),
            default => $this->out->report->add(Omitted::Image, 'missing'),
        };
    }

    private function note(Element $note, Line $line): void
    {
        $html = $this->text(Xml::child($note, self::TEXT, 'note-body'));
        $line->inline->html($this->out->note($html, 'endnote' === $note->getAttributeNS(self::TEXT, 'note-class')));
    }

    /**
     * The text of a table cell, a list item or a note: its paragraphs as inline HTML joined by line breaks.
     */
    private function text(?Element $container): string
    {
        $lines = [];

        foreach ($container->children ?? [] as $node) {
            $lines[] = $this->piece($node);
        }

        return $this->join($lines);
    }

    /**
     * The text of one element of a cell, an item or a note.
     */
    private function piece(Element $node): string
    {
        return match ([$node->namespaceURI, $node->localName]) {
            [self::TEXT, 'p'], [self::TEXT, 'h'] => $this->inlineOf($node),
            [self::TEXT, 'list'], [self::TEXT, 'list-item'], [self::TEXT, 'list-header'], [self::TEXT, 'section'] => $this->text($node),
            [self::TABLE, 'table'] => $this->nestedTable($node),
            default => '',
        };
    }

    /**
     * @param list<string> $lines
     */
    private function join(array $lines): string
    {
        return implode('<br>', array_filter($lines, static fn (string $line): bool => '' !== $line));
    }

    private function inlineOf(Element $paragraph): string
    {
        $line = new Line();
        $this->inline($paragraph, $line, $this->styles->directMarks('paragraph', $paragraph->getAttributeNS(self::TEXT, 'style-name') ?? ''), null);

        return $line->inline->toHtml();
    }

    /**
     * A list and the lists nested in its items, as items of one list block: the paragraphs of an item joined by line
     * breaks.
     */
    private function list(Element $list, int $level, string $style, string $key): void
    {
        $style = $list->getAttributeNS(self::TEXT, 'style-name') ?? $style;
        $kind = $this->styles->list($style);

        foreach (Xml::children($list, self::TEXT) as $item) {
            $lines = [];

            foreach (Xml::children($item, self::TEXT) as $node) {
                if ('list' === $node->localName) {
                    $this->item($key, $level, $lines, $kind);
                    $lines = [];
                    $this->list($node, $level + 1, $style, $key);

                    continue;
                }

                $lines[] = $this->piece($node);
            }

            $this->item($key, $level, $lines, $kind);
        }
    }

    /**
     * @param list<string>               $lines
     * @param array{bool, ?string, ?int} $kind
     */
    private function item(string $key, int $level, array $lines, array $kind): void
    {
        $html = $this->join($lines);

        if ('' !== $html) {
            $this->out->listItem($key, $level, $html, ...$kind);
        }
    }

    private function nestedTable(Element $table): string
    {
        $this->out->report->add(Omitted::NestedTable);
        $cells = [];

        foreach ($table->getElementsByTagNameNS(self::TABLE, 'table-cell') as $cell) {
            $cells[] = $this->text($cell);
        }

        return trim(implode(' ', $cells));
    }

    private function table(Element $table): void
    {
        $rows = [];
        $headings = $this->rows($table, $rows);

        if ([] !== $rows) {
            $this->out->table($rows, $headings);
        }
    }

    /**
     * The rows of a table or of a group of rows, added to the list, a cell spanning columns or rows followed by the
     * covered cells that keep their places; whether the first rows are header rows.
     *
     * @param list<list<string>> $rows
     */
    private function rows(Element $group, array &$rows): bool
    {
        $headings = false;

        foreach (Xml::children($group, self::TABLE) as $node) {
            if ('table-header-rows' === $node->localName) {
                $headings = [] === $rows;
            }

            if ('table-row' === $node->localName) {
                $rows = array_pad($rows, \count($rows) + $this->repeat($node, 'number-rows-repeated'), $this->cells($node));
            } elseif (\in_array($node->localName, ['table-header-rows', 'table-rows', 'table-row-group'], true)) {
                $this->rows($node, $rows);
            }
        }

        return $headings;
    }

    /**
     * @return list<string>
     */
    private function cells(Element $row): array
    {
        $cells = [];

        foreach (Xml::children($row, self::TABLE) as $cell) {
            $covered = 'covered-table-cell' === $cell->localName;

            if ($covered) {
                $this->out->report->add(Omitted::MergedCell);
            }

            $cells = array_pad($cells, \count($cells) + $this->repeat($cell, 'number-columns-repeated'), $covered ? '' : $this->text($cell));
        }

        return $cells;
    }

    private function repeat(Element $element, string $attribute): int
    {
        return max(1, min(self::REPEAT, (int) $element->getAttributeNS(self::TABLE, $attribute)));
    }
}
