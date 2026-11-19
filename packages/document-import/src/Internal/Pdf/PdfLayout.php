<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Internal\Builder;
use Phil\DocumentImport\Internal\Inline;
use Phil\DocumentImport\Internal\Mark;
use Phil\DocumentImport\Internal\Role;
use Phil\DocumentImport\Media;

/**
 * The lines of the pages written as blocks, from their sizes and places alone: the most common size is the body
 * text; larger sizes few characters have are headings, the largest first, and a short line alone in bold is a heading below them; lines
 * that start with a bullet or a number are list items, nested by their indentation; other lines close together are
 * one paragraph, also across a page when the sentence goes on, a word cut by a hyphen at the end of a line joined
 * again. Bold, italic, exponents and links are kept.
 *
 * @internal
 */
final class PdfLayout
{
    private const string BULLET = '/^([•◦▪▫‣⁃●○■□–\-*·])\s+/u';

    private const string NUMBER = '/^(\(?(?:\d{1,3}|[a-zA-Z]|[ivxlcdm]{1,6})[.)])\s+/u';

    private float $body = 0.0;

    /** @var list<float> */
    private array $headings = [];

    /** @var list<PdfLine> the lines of the block being gathered */
    private array $block = [];

    private string $kind = '';

    private int $list = 0;

    /** Whether the items of the current list are numbered, `null` before its first item. */
    private ?bool $numbered = null;

    /** @var list<float> the indentation of each open level of the current list */
    private array $indents = [];

    public function __construct(private readonly Builder $out)
    {
    }

    /**
     * The runs of a page gathered into lines.
     *
     * @param list<PdfRun> $runs
     *
     * @return list<PdfLine>
     */
    public static function lines(array $runs, int $page): array
    {
        $lines = [];
        $line = null;

        foreach ($runs as $run) {
            if ($run->image instanceof Media) {
                $lines[] = $line = new PdfLine($page, $run->x, $run->y, $run, $run->image);

                continue;
            }

            if ($line instanceof PdfLine && $line->holds($run)) {
                $line->add($run);

                continue;
            }

            if ('' === trim($run->text)) {
                continue;
            }

            $lines[] = $line = new PdfLine($page, $run->x, $run->y, $run);
        }

        return $lines;
    }

    /**
     * @param list<PdfLine> $lines the lines of every page, in reading order
     */
    public function write(array $lines): void
    {
        $this->sizes($lines);
        $previous = null;

        foreach ($lines as $line) {
            $kind = $this->classify($line);

            if (null === $previous || !$this->continues($previous, $line, $kind)) {
                $this->flush();
                $this->kind = $kind;
            }

            $this->block[] = $line;
            $previous = $line;
        }

        $this->flush();
    }

    /**
     * The size of the body text, the one most characters have, and the larger sizes of headings, largest first.
     *
     * @param list<PdfLine> $lines
     */
    private function sizes(array $lines): void
    {
        $weights = [];

        foreach ($lines as $line) {
            $key = (string) (round($line->size * 2) / 2);
            $weights[$key] = ($weights[$key] ?? 0) + mb_strlen($line->text());
        }

        // Without text, the body size is 0 and no line is a heading.
        arsort($weights);
        $this->body = (float) array_key_first($weights);
        $headings = [];

        foreach ($lines as $line) {
            $size = round($line->size * 2) / 2;

            if ($size >= $this->body * 1.15 && mb_strlen($line->text()) <= 200 && !$line->image instanceof Media && $weights[(string) $size] <= 0.25 * $weights[(string) $this->body]) {
                $headings[(string) $size] = $size;
            }
        }

        rsort($headings);
        $this->headings = $headings;
    }

    /**
     * What a line starts: an image, a heading, a list item or text.
     */
    private function classify(PdfLine $line): string
    {
        $text = ltrim($line->text());

        return match (true) {
            $line->image instanceof Media => 'image',
            \in_array(round($line->size * 2) / 2, $this->headings, true) => 'heading',
            1 === preg_match(self::BULLET, $text), 1 === preg_match(self::NUMBER, $text) => 'item',
            default => 'text',
        };
    }

    /**
     * The level of a heading line, from 1 for the largest heading size.
     */
    private function level(PdfLine $line): int
    {
        return 1 + (int) array_search(round($line->size * 2) / 2, $this->headings, true);
    }

    /**
     * Whether a list item is numbered: its marker no bullet.
     */
    private function ordered(PdfLine $line): bool
    {
        return 1 !== preg_match(self::BULLET, ltrim($line->text()));
    }

    /**
     * Whether a line goes on with the block of the line before it.
     */
    private function continues(PdfLine $previous, PdfLine $line, string $kind): bool
    {
        if ('image' === $kind || 'image' === $this->kind || ('item' === $kind) || ('heading' === $this->kind) !== ('heading' === $kind)) {
            return false;
        }

        if ('heading' === $kind && abs($line->size - $previous->size) > 0.5) {
            return false;
        }

        if ($line->page !== $previous->page) {
            return 'text' === $this->kind && 1 !== preg_match('/[.!?:;»”"]\s*$/u', $previous->text()) && 1 === preg_match('/^\s*\p{Ll}/u', $line->text());
        }

        $gap = $previous->y - $line->y;

        return $gap > 0 && $gap <= 1.8 * max($line->size, $previous->size) && abs($line->size - $previous->size) <= 0.2 * $previous->size && ('item' !== $this->kind || $line->x > $this->block[0]->x + 1);
    }

    private function flush(): void
    {
        if ([] === $this->block) {
            return;
        }

        $lines = $this->block;
        $this->block = [];
        $first = $lines[0];

        if ($first->image instanceof Media) {
            $this->list = $this->interrupt();
            $this->out->image($first->image);
        } else {
            $this->text($lines, $first);
        }
    }

    /**
     * Lines of text written as a list item, a heading or a paragraph; nothing when they hold no text.
     *
     * @param list<PdfLine> $lines
     */
    private function text(array $lines, PdfLine $first): void
    {
        $html = $this->html($lines, 'item' === $this->kind);

        if ('' === $html) {
            return;
        }

        if ('item' === $this->kind) {
            $ordered = $this->ordered($first);

            if (null !== $this->numbered && $this->numbered !== $ordered) {
                $this->list = $this->interrupt();
            }

            $this->numbered = $ordered;
            $this->out->listItem((string) $this->list, $this->indent($first->x), $html, $ordered);

            return;
        }

        $this->list = $this->interrupt();
        $alone = 1 === \count($lines) && $first->isBold() && mb_strlen(trim($first->text())) <= 100 && 1 !== preg_match('/[.,;:]\s*$/u', $first->text()) && [] !== $this->headings;

        match (true) {
            'heading' === $this->kind => $this->out->block(Role::heading($this->level($first)), strip_tags($html, '<i><sup><a>')),
            $alone => $this->out->block(Role::heading(\count($this->headings) + 1), strip_tags($html, '<i><sup><a>')),
            default => $this->out->paragraph($html),
        };
    }

    /**
     * The next list, a block having come between it and the list before.
     */
    private function interrupt(): int
    {
        $this->indents = [];
        $this->numbered = null;

        return $this->list + 1;
    }

    /**
     * The level of a list item from its indentation: deeper than the item before by more than a few points, or back
     * at the level of an item above.
     */
    private function indent(float $x): int
    {
        while ([] !== $this->indents && $x < $this->indents[\count($this->indents) - 1] - 3) {
            array_pop($this->indents);
        }

        if ([] === $this->indents || $x > $this->indents[\count($this->indents) - 1] + 3) {
            $this->indents[] = $x;
        }

        return min(8, \count($this->indents) - 1);
    }

    /**
     * The inline HTML of lines joined by spaces, a hyphen at the end of a line joining the word cut; the marker of a
     * list item left out.
     *
     * @param list<PdfLine> $lines
     */
    private function html(array $lines, bool $item): string
    {
        $inline = new Inline();
        $last = '';

        foreach ($lines as $index => $line) {
            foreach ($line->runs as $position => [$run, $raised]) {
                $text = (string) preg_replace('/\s+/u', ' ', $run->text);

                if ($item && 0 === $index && 0 === $position) {
                    $text = (string) preg_replace([self::BULLET, self::NUMBER], '', ltrim($text), 1);
                }

                if (0 === $position && $index > 0) {
                    $text = $this->joined($last, $text, $inline);
                }

                $inline->text($text, Mark::tags($this->marks($run, $raised), $run->link));
                $last = '' === $text ? $last : $text;
            }
        }

        return (string) preg_replace('/ {2,}/', ' ', $inline->toHtml());
    }

    /**
     * @return list<Mark>
     */
    private function marks(PdfRun $run, bool $raised): array
    {
        $marks = [];

        if ($run->bold) {
            $marks[] = Mark::Bold;
        }

        if ($run->italic) {
            $marks[] = Mark::Italic;
        }

        if ($raised) {
            $marks[] = Mark::Superscript;
        }

        return $marks;
    }

    /**
     * The first text of a line after the last text of the line before: after a space, or glued to a word cut by a
     * hyphen.
     */
    private function joined(string $last, string $text, Inline $inline): string
    {
        if (1 === preg_match('/\p{L}-$/u', $last) && 1 === preg_match('/^\p{Ll}/u', $text)) {
            $inline->dropLast(1);

            return $text;
        }

        return str_ends_with($last, ' ') || str_starts_with($text, ' ') ? $text : ' '.$text;
    }
}
