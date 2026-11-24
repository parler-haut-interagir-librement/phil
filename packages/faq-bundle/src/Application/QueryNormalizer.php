<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use Normalizer;

/**
 * Turns a search into the form it is matched and counted in: the compatibility forms folded, the accents and the case
 * dropped, every run of what is neither a letter nor a digit written as one space, at most 100 characters and 10
 * words. `  Où est  MA commande ?` is `ou est ma commande`.
 *
 * Only the first 1,000 bytes of a search are read, so that a long one costs no more than a short one.
 */
final readonly class QueryNormalizer
{
    /** The longest normalized search, in characters. */
    public const int MAX_LENGTH = 100;

    /** The most words a search is matched on. */
    public const int MAX_TERMS = 10;

    /** The most bytes of a search read. */
    public const int MAX_INPUT_BYTES = 1_000;

    public function normalize(string $text): string
    {
        return trim(mb_substr($this->fold(substr($text, 0, self::MAX_INPUT_BYTES)), 0, self::MAX_LENGTH, 'UTF-8'));
    }

    /**
     * A whole text in the form searches are matched against, without the limits of a search: the wording and the answer
     * of a question.
     */
    public function fold(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');
        $folded = Normalizer::normalize($text, Normalizer::FORM_KD);
        $folded = preg_replace('/\p{Mn}+/u', '', \is_string($folded) ? $folded : $text) ?? '';
        $folded = preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($folded, 'UTF-8')) ?? '';

        return trim($folded);
    }

    /**
     * The distinct words of a normalized search, at most ten.
     *
     * @return list<string>
     */
    public function terms(string $normalized): array
    {
        if ('' === $normalized) {
            return [];
        }

        return \array_slice(array_unique(explode(' ', $normalized)), 0, self::MAX_TERMS);
    }

    /**
     * Whether a normalized text holds every word.
     *
     * @param list<string> $terms
     */
    public function matches(string $normalizedText, array $terms): bool
    {
        return array_all($terms, static fn (string $term): bool => str_contains($normalizedText, $term));
    }

    /**
     * The text of HTML, its tags dropped and its entities read, for a search or a plain export: the blocks and the line
     * breaks keep their words apart.
     */
    public static function textOf(string $html): string
    {
        $spaced = preg_replace('#<(/?(?:address|blockquote|br|dd|div|dl|dt|figcaption|figure|h[1-6]|hr|li|ol|p|pre|section|table|td|th|tr|ul)\b)#i', ' <$1', $html) ?? $html;
        $text = html_entity_decode(strip_tags($spaced), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }
}
