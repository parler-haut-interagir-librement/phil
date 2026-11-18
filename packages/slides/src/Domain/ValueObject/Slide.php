<?php

declare(strict_types=1);

namespace Phil\Slides\Domain\ValueObject;

use Phil\Ujo\Block;

/**
 * One slide: its number in the deck, the blocks the audience sees, the speaker's notes — blocks and plain texts — that
 * only the presenter sees, its own classes, whether a `pageNumber` block skips its number, its title, the text of its first
 * heading, and whether the directives of its notes show its number (`null` when the deck decides).
 */
final readonly class Slide
{
    /**
     * @param list<Block>  $content   the blocks shown, without any note
     * @param list<Block>  $notes     the blocks of its notes containers
     * @param list<string> $noteTexts the plain texts of the `note` tunes of its blocks
     * @param list<string> $classes
     */
    public function __construct(
        public int $number,
        public array $content,
        public array $notes = [],
        public array $noteTexts = [],
        public array $classes = [],
        public bool $paginated = true,
        public ?string $title = null,
        public ?bool $pagination = null,
    ) {
    }

    /**
     * Whether the slide shows its number: never when a `pageNumber` block skips it, else as the directives of its notes
     * say, else as the deck says.
     */
    public function showsNumber(bool $deckPaginates): bool
    {
        return $this->paginated && ($this->pagination ?? $deckPaginates);
    }

    public function hasNotes(): bool
    {
        return [] !== $this->notes || [] !== $this->noteTexts;
    }

    /**
     * The anchor of the slide in a page, `slide-3`.
     */
    public function anchor(): string
    {
        return 'slide-'.$this->number;
    }
}
