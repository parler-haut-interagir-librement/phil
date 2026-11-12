<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Domain\ValueObject;

/**
 * How words are compared: in lower case, every run of white space one space, nothing around them. The words of a
 * query, the phrases of a synonym and the text of the database index are all read this way.
 */
final readonly class Phrase
{
    public static function normalize(string $text): string
    {
        $lower = mb_strtolower($text);

        return trim(preg_replace('/\s+/u', ' ', $lower) ?? $lower);
    }

    /**
     * The words of a normalized phrase, in their order.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $normalized = self::normalize($text);

        return '' === $normalized ? [] : explode(' ', $normalized);
    }
}
