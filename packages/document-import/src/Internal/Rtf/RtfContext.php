<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Rtf;

use Phil\DocumentImport\Internal\Inline;

/**
 * Where the text being read goes: the body of the document, or a footnote whose paragraphs are gathered until it
 * closes. It holds the paragraph being read and its properties — its style, its outline level, its list and level,
 * its alignment, whether it sits in a table, the marker of its list item — and the rows of the table being read.
 *
 * @internal
 */
final class RtfContext
{
    public Inline $inline;

    /** @var list<string> the paragraphs of a footnote */
    public array $lines = [];

    public bool $endnote = false;

    public int $style = 0;

    public ?int $outline = null;

    public ?int $list = null;

    public int $level = 0;

    public ?string $alignment = null;

    public bool $inTable = false;

    public ?string $marker = null;

    /** @var list<string> the paragraphs of the cell being read */
    public array $cell = [];

    /** @var list<string> the cells of the row being read */
    public array $row = [];

    /** @var list<list<string>> */
    public array $rows = [];

    public bool $headerRow = false;

    public bool $headings = false;

    public function __construct(public readonly bool $body)
    {
        $this->inline = new Inline();
    }

    /**
     * The paragraph properties back to their defaults, as `\pard` sets them.
     */
    public function resetParagraph(): void
    {
        $this->style = 0;
        $this->outline = null;
        $this->list = null;
        $this->level = 0;
        $this->alignment = null;
        $this->inTable = false;
    }

    /**
     * The text of the paragraph read so far as inline HTML, and a new paragraph started.
     */
    public function take(): string
    {
        $html = $this->inline->isBlank() ? '' : $this->inline->toHtml();
        $this->inline = new Inline();

        return $html;
    }

    public function endCell(): void
    {
        $this->cell[] = $this->take();
        $this->row[] = implode('<br>', array_filter($this->cell, static fn (string $line): bool => '' !== $line));
        $this->cell = [];
    }

    public function endRow(): void
    {
        if ([] !== $this->cell || !$this->inline->isBlank()) {
            $this->endCell();
        }

        if ([] !== $this->row) {
            $this->headings = $this->headings || ([] === $this->rows && $this->headerRow);
            $this->rows[] = $this->row;
        }

        $this->row = [];
        $this->headerRow = false;
    }
}
