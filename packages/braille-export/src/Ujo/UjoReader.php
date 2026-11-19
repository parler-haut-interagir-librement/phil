<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Ujo;

use Phil\BrailleExport\Contract\Block as BrailleBlock;
use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\ListBlock;
use Phil\BrailleExport\Document\ListItem;
use Phil\BrailleExport\Document\PageBreak;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Document\Table;
use Phil\Ujo\Block;
use Phil\Ujo\Document as UjoDocument;
use Phil\Ujo\Text\PlainText;

/**
 * A UJO (Universal JSON Output) document read as a document to put into braille.
 *
 * Headings, paragraphs, lists, tables and page breaks keep their structure; a quote becomes a paragraph followed by
 * its caption; a delimiter, which braille readers do not need, is left out. Every other block becomes the paragraphs
 * of its plain text, as `phil/ujo` writes it, and gives nothing when it has none, such as a chart.
 */
final readonly class UjoReader
{
    private PlainText $text;

    public function __construct(?PlainText $text = null)
    {
        $this->text = $text ?? new PlainText();
    }

    /**
     * @param string $language the BCP 47 tag of the text of the document
     */
    public function read(UjoDocument $document, string $language, string $title = ''): Document
    {
        $blocks = [];

        foreach ($document->blocks as $block) {
            array_push($blocks, ...$this->block($block));
        }

        return new Document($blocks, $language, $title);
    }

    /**
     * @return list<BrailleBlock>
     */
    private function block(Block $block): array
    {
        $data = $block->data;

        return match ($block->type) {
            'header' => [new Heading($this->text->text($data['text'] ?? null), $this->level($data['level'] ?? null))],
            'paragraph' => [new Paragraph($this->text->text($data['text'] ?? null))],
            'list' => [$this->list($data['items'] ?? null, $data['style'] ?? null, $data['meta'] ?? null)],
            'table' => [$this->table($data)],
            'quote' => [new Paragraph($this->text->text($data['text'] ?? null)), new Paragraph($this->text->text($data['caption'] ?? null))],
            'pageBreak' => [new PageBreak()],
            'delimiter' => [],
            default => $this->paragraphs($this->text->block($block)),
        };
    }

    private function list(mixed $items, mixed $style, mixed $meta): ListBlock
    {
        $read = [];

        foreach (\is_array($items) ? $items : [] as $item) {
            if (\is_array($item)) {
                $children = $item['items'] ?? null;
                $read[] = new ListItem(
                    $this->text->text($item['content'] ?? null),
                    \is_array($children) && [] !== $children ? $this->list($children, $style, null) : null,
                );
            }
        }

        $start = \is_array($meta) ? $meta['start'] ?? 1 : 1;

        return new ListBlock($read, 'ordered' === $style, \is_int($start) ? max(0, $start) : 1);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function table(array $data): Table
    {
        $rows = [];

        foreach (\is_array($data['content'] ?? null) ? $data['content'] : [] as $row) {
            $cells = [];

            foreach (\is_array($row) ? $row : [] as $cell) {
                $cells[] = $this->text->text(\is_array($cell) ? $cell['text'] ?? null : $cell);
            }

            $rows[] = $cells;
        }

        return new Table($rows, true === ($data['withHeadings'] ?? false), $this->text->text($data['caption'] ?? null));
    }

    /**
     * @return list<Paragraph>
     */
    private function paragraphs(string $text): array
    {
        $paragraphs = [];

        $parts = preg_split('/\n\s*\n/', $text);

        foreach (false === $parts ? [] : $parts as $paragraph) {
            if ('' !== trim($paragraph)) {
                $paragraphs[] = new Paragraph($paragraph);
            }
        }

        return $paragraphs;
    }

    private function level(mixed $level): int
    {
        return \is_int($level) ? max(1, min(6, $level)) : 2;
    }
}
