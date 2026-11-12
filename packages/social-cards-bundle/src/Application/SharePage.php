<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application;

/**
 * The share metadata the current request sets for its page — from the controller, or from a template with
 * `social_page()` —, with the keys of `ShareMetadata::KEYS`, plus `card_template`, the card template to draw the page's
 * card with, and `card`, false to draw none. Reset between requests.
 */
final class SharePage
{
    /** @var array<string, mixed> */
    private array $values = [];

    /**
     * Sets values over those set before; a null value unsets one.
     *
     * @param array<string, mixed> $values
     */
    public function set(array $values): void
    {
        $this->values = array_filter([...$this->values, ...$values], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return $this->values;
    }

    public function reset(): void
    {
        $this->values = [];
    }
}
