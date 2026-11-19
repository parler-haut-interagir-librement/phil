<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Phil\DocumentImport\Media;

/**
 * A line of a page: the runs on one baseline, in the order they were drawn, with where it starts and ends, its
 * baseline, its size — the largest of its runs — and the page it is on; or an image alone.
 *
 * @internal
 */
final class PdfLine
{
    /** @var list<array{PdfRun, bool}> each run, and whether it is raised as an exponent */
    public private(set) array $runs = [];

    /** The text of the runs, kept as they are added so that a line of many runs is read once. */
    private string $text;

    public float $end;

    public float $size;

    public function __construct(
        public readonly int $page,
        public readonly float $x,
        public readonly float $y,
        PdfRun $first,
        public readonly ?Media $image = null,
    ) {
        $this->runs[] = [$first, false];
        $this->text = $first->text;
        $this->end = $first->end;
        $this->size = $first->size;
    }

    /**
     * Whether a run sits on this line: its baseline within half a size of the line's, or raised above it as an
     * exponent.
     */
    public function holds(PdfRun $run): bool
    {
        return !$this->image instanceof Media && abs($run->y - $this->y) <= 0.5 * max($run->size, $this->size);
    }

    public function add(PdfRun $run): void
    {
        $gap = $run->x - $this->end;

        if ($gap > 0.15 * $run->size && '' !== $this->text && !str_ends_with($this->text, ' ') && !str_starts_with($run->text, ' ')) {
            $this->runs[] = [new PdfRun(' ', $this->end, $run->x, $run->y, $run->size, link: $run->link), false];
            $this->text .= ' ';
        }

        $this->text .= $run->text;
        $this->runs[] = [$run, $run->size < 0.85 * $this->size && $run->y - $this->y > 0.15 * $this->size];
        $this->end = max($this->end, $run->end);
        $this->size = max($this->size, $run->size);
    }

    public function text(): string
    {
        return $this->text;
    }

    /**
     * Whether every run with letters is bold.
     */
    public function isBold(): bool
    {
        $lettered = array_filter($this->runs, static fn (array $run): bool => '' !== trim($run[0]->text));

        return [] !== $lettered && array_all($lettered, static fn (array $run): bool => $run[0]->bold);
    }
}
