<?php

declare(strict_types=1);

namespace Phil\Slides\Domain;

use Phil\Ujo\Block;

/**
 * The slide the slicer is filling, until a separator or a heading closes it.
 *
 * @internal
 */
final class SlideDraft
{
    /** @var list<Block> */
    public array $content = [];

    /** @var list<Block> */
    public array $notes = [];

    /** @var list<string> */
    public array $noteTexts = [];

    /** @var list<string> */
    public array $classes = [];

    public bool $paginated = true;

    /** @var list<string>|null the classes the directives of a note give this slide alone */
    public ?array $spotClasses = null;

    /** @var list<string> the classes the directives of a note give this slide and the ones after */
    public array $carriedClasses = [];

    public ?bool $spotPagination = null;

    public ?bool $carriedPagination = null;

    /** @var list<string> the directives of the notes read so far that were not kept */
    public array $ignored = [];

    /**
     * The slide after this one, with what the directives carry from slide to slide.
     */
    public function next(): self
    {
        $next = new self();
        $next->carriedClasses = $this->carriedClasses;
        $next->carriedPagination = $this->carriedPagination;
        $next->ignored = $this->ignored;

        return $next;
    }

    /**
     * Whether the slide shows its number, `null` when the deck decides.
     */
    public function pagination(): ?bool
    {
        return $this->spotPagination ?? $this->carriedPagination;
    }

    /**
     * The classes of the slide: those of the directives — its own, else those carried —, then those of its `pageStyle`.
     *
     * @return list<string>
     */
    public function allClasses(): array
    {
        return array_values(array_unique([...$this->spotClasses ?? $this->carriedClasses, ...$this->classes]));
    }

    /**
     * Whether the slide holds a block the audience sees.
     */
    public function shows(): bool
    {
        return [] !== $this->content;
    }
}
