<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Layout\Internal;

use Phil\BrailleExport\Document\Document;
use Phil\BrailleExport\Document\Heading;
use Phil\BrailleExport\Document\ListBlock;
use Phil\BrailleExport\Document\PageBreak;
use Phil\BrailleExport\Document\Paragraph;
use Phil\BrailleExport\Document\PrintPage;
use Phil\BrailleExport\Document\SectionBreak;
use Phil\BrailleExport\Document\Table;

/**
 * A document read into the steps of its layout and the texts to translate, so that the whole document goes to the
 * translator in one batch.
 *
 * @internal
 */
final class Prepared
{
    /** The levels of nesting of a list that move it further in; deeper ones stay at the last. */
    private const int MAX_DEPTH = 8;

    /** @var list<string> */
    public private(set) array $texts = [];

    /** @var list<Entry> */
    public private(set) array $entries = [];

    private function __construct(private readonly bool $french)
    {
    }

    public static function of(Document $document): self
    {
        $prepared = new self('fr' === strtolower(substr($document->language, 0, 2)));

        foreach ($document->blocks as $block) {
            match (true) {
                $block instanceof Heading => $prepared->heading($block),
                $block instanceof Paragraph => $prepared->text($block->text, 2, 0),
                $block instanceof ListBlock => $prepared->list($block, 0),
                $block instanceof Table => $prepared->table($block),
                $block instanceof PageBreak => $prepared->entries[] = new Entry(Entry::PAGE_BREAK),
                $block instanceof SectionBreak => $prepared->entries[] = new Entry(Entry::SECTION_BREAK),
                $block instanceof PrintPage => $prepared->printPage($block),
                default => null,
            };
        }

        return $prepared;
    }

    /**
     * The lines of a text, each with its white space folded to single spaces and trimmed; empty lines left out.
     *
     * @return list<string>
     */
    private function lines(string $text): array
    {
        $lines = [];

        $split = preg_split('/\R/u', mb_scrub($text, 'UTF-8'));

        foreach (false === $split ? [] : $split as $line) {
            $line = trim((string) preg_replace('/[\s\p{Cc}\p{Cf}\p{Z}]+/u', ' ', $line));

            if ('' !== $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function heading(Heading $heading): void
    {
        $line = implode(' ', $this->lines($heading->text));

        if ('' === $line) {
            return;
        }

        $indent = match ($heading->level) {
            1 => 0,
            2 => 4,
            default => 6,
        };
        $this->entries[] = new Entry(Entry::HEADING, [$this->add($line)], $indent, $indent, $heading->level);
    }

    private function text(string $text, int $indent, int $runover): void
    {
        $texts = array_map($this->add(...), $this->lines($text));

        if ([] !== $texts) {
            $this->entries[] = new Entry(Entry::TEXT, $texts, $indent, $runover);
        }
    }

    private function list(ListBlock $list, int $depth): void
    {
        $indent = 2 * min($depth, self::MAX_DEPTH);

        foreach ($list->items as $position => $item) {
            $marker = $list->ordered ? ($list->start + $position).'. ' : '';
            $this->text($marker.$item->text, $indent, $indent + 2);

            if ($item->children instanceof ListBlock) {
                $this->list($item->children, $depth + 1);
            }
        }
    }

    private function table(Table $table): void
    {
        $this->text($table->caption, 2, 0);
        $rows = $table->rows;
        $headings = $table->headerRow && \count($rows) > 1 ? array_shift($rows) : [];
        $separator = $this->french ? ' : ' : ': ';

        foreach ($rows as $row) {
            $cells = [];

            foreach ($row as $column => $cell) {
                $cell = implode(' ', $this->lines($cell));
                $heading = implode(' ', $this->lines($headings[$column] ?? ''));

                if ('' !== $cell) {
                    $cells[] = '' === $heading ? $cell : $heading.$separator.$cell;
                }
            }

            $this->text(implode('; ', $cells), 0, 2);
        }
    }

    private function printPage(PrintPage $page): void
    {
        $number = implode(' ', $this->lines($page->number));

        if ('' !== $number) {
            $this->entries[] = new Entry(Entry::PRINT_PAGE, [$this->add($number)], raw: $number);
        }
    }

    private function add(string $text): int
    {
        $this->texts[] = $text;

        return \count($this->texts) - 1;
    }
}
