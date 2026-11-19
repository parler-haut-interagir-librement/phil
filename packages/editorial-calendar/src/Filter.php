<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar;

/**
 * Which items a view or a list shows: by author, by section and by state. Each criterion left empty accepts every
 * item; an item is shown when it passes all of them. Immutable: each change gives a new filter.
 */
final readonly class Filter
{
    /**
     * @param array<array-key, true>      $authors  the author identifiers accepted, as keys
     * @param array<array-key, true>      $sections the sections accepted, as keys
     * @param array<array-key, ItemState> $states   the states accepted
     */
    private function __construct(
        private array $authors = [],
        private array $sections = [],
        private array $states = [],
    ) {
    }

    /**
     * A filter that accepts every item.
     */
    public static function all(): self
    {
        return new self();
    }

    /**
     * The filter accepting only the items of these authors.
     */
    public function byAuthors(AuthorId ...$authors): self
    {
        return new self(array_fill_keys(array_map(static fn (AuthorId $author): string => $author->value, $authors), true), $this->sections, $this->states);
    }

    /**
     * The filter accepting only the items of these sections; an item without a section is never among them.
     */
    public function bySections(string ...$sections): self
    {
        return new self($this->authors, array_fill_keys($sections, true), $this->states);
    }

    /**
     * The filter accepting only the items in these states.
     */
    public function byStates(ItemState ...$states): self
    {
        return new self($this->authors, $this->sections, $states);
    }

    /**
     * Whether the filter accepts every item.
     */
    public function isEmpty(): bool
    {
        return [] === $this->authors && [] === $this->sections && [] === $this->states;
    }

    public function accepts(PlannedItem $item): bool
    {
        return ([] === $this->authors || isset($this->authors[$item->author->value]))
            && ([] === $this->sections || (null !== $item->section && isset($this->sections[$item->section])))
            && ([] === $this->states || \in_array($item->state, $this->states, true));
    }
}
