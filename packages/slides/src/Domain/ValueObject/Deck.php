<?php

declare(strict_types=1);

namespace Phil\Slides\Domain\ValueObject;

use Phil\Slides\Domain\Exception\InvalidOption;

/**
 * The slides a document is cut into, in order, with the settings of the whole deck, an identifier that names the deck
 * between the windows that show it, and the directives of the source that were not kept.
 */
final readonly class Deck
{
    private const string ID = '/^[A-Za-z0-9_.:-]{1,128}$/D';

    /**
     * @param list<Slide>  $slides
     * @param list<string> $ignoredDirectives the names of the directives unknown, or whose value was refused
     */
    public function __construct(
        public string $id,
        public array $slides,
        public DeckSettings $settings = new DeckSettings(),
        public array $ignoredDirectives = [],
    ) {
        if (1 !== preg_match(self::ID, $id)) {
            throw InvalidOption::because(\sprintf('The identifier of a deck is 1 to 128 letters, digits, "_", "-", "." or ":"; "%s" is not.', $id));
        }
    }

    public function count(): int
    {
        return \count($this->slides);
    }

    /**
     * The slide of a number, from 1; `null` when the deck has none of that number.
     */
    public function slide(int $number): ?Slide
    {
        return $this->slides[$number - 1] ?? null;
    }

    public function hasNotes(): bool
    {
        return array_any($this->slides, static fn (Slide $slide): bool => $slide->hasNotes());
    }

    /**
     * The same deck under another identifier: the one an application gives it, stable across edits.
     */
    public function withId(string $id): self
    {
        return new self($id, $this->slides, $this->settings, $this->ignoredDirectives);
    }
}
