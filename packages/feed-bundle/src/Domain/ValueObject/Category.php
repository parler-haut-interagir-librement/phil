<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Domain\ValueObject;

use Phil\FeedBundle\Domain\Exception\InvalidFeed;

/**
 * A subject an entry is filed under: a term, as a machine reads it, and perhaps a label, as a reader does.
 *
 * Atom writes both, RSS the label or else the term, JSON Feed the label or else the term as a tag.
 */
final readonly class Category
{
    public const int MAX_LENGTH = 255;

    public string $term;

    public ?string $label;

    /**
     * @throws InvalidFeed when the term or the label is empty, longer than 255 characters or holds a control character
     */
    public function __construct(string $term, ?string $label = null)
    {
        $this->term = Text::line('category term', $term, self::MAX_LENGTH);
        $this->label = null === $label ? null : Text::line('category label', $label, self::MAX_LENGTH);
    }

    /**
     * What a reader sees: the label, else the term.
     */
    public function display(): string
    {
        return $this->label ?? $this->term;
    }
}
