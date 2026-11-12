<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

use Phil\AnalyticsBundle\Domain\ValueObject\CounterKey;

/**
 * A term searched on a site, normalized for a counter, or nothing.
 *
 * A term that looks like it names someone — an address, a long run of digits, a link — is not counted. The small-number
 * rule then shows only the frequent ones on read, and the rollup forgets the rare ones.
 */
final readonly class SearchTerm
{
    public const int MIN_CHARACTERS = 2;

    public const int MAX_CHARACTERS = 64;

    /**
     * The counter value of a searched text: lower case, single spaces; `null` when it must not be counted.
     */
    public static function valueOf(string $text): ?string
    {
        $spaced = preg_replace('/\s+/u', ' ', $text);

        if (null === $spaced) {
            return null; // not UTF-8
        }

        $term = mb_strtolower(trim($spaced));
        $length = mb_strlen($term);

        if (1 === preg_match('/\p{C}/u', $term) || $length < self::MIN_CHARACTERS || $length > self::MAX_CHARACTERS || \strlen($term) > CounterKey::MAX_VALUE_LENGTH) {
            return null;
        }

        return str_contains($term, '@') || str_contains($term, '://') || 1 === preg_match('/\d{6}/', $term) ? null : $term;
    }
}
