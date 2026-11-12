<?php

declare(strict_types=1);

namespace Phil\SocialCardsBundle\Application;

use Phil\SocialCards\Card\CardContent;
use Phil\SocialCards\Exception\SocialCardsException;

/**
 * What a card is drawn from: the name of its template and its content, kept as JSON until the card is drawn.
 */
final readonly class CardRequest
{
    public function __construct(
        public string $template,
        public CardContent $content,
    ) {
    }

    /**
     * @return array{template: string, title: string, subtitle: string|null, locale: string}
     */
    public function toArray(): array
    {
        return ['template' => $this->template, ...$this->content->toArray()];
    }

    /**
     * The request kept as an array, or null when the array is not one.
     *
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): ?self
    {
        ['template' => $template, 'title' => $title, 'subtitle' => $subtitle, 'locale' => $locale] = $values + ['template' => null, 'title' => null, 'subtitle' => null, 'locale' => null];

        if (!\is_string($template) || !\is_string($title) || !\is_string($locale) || (null !== $subtitle && !\is_string($subtitle))) {
            return null;
        }

        try {
            return new self($template, new CardContent($title, $subtitle, $locale));
        } catch (SocialCardsException) {
            return null;
        }
    }
}
