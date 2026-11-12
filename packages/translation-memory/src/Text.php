<?php

declare(strict_types=1);

namespace Phil\TranslationMemory;

/**
 * The texts of segments are inline HTML — the fields of a UJO document. This reads them as words: the plain text with
 * its tags left out and its character references decoded, and the normalized form both fingerprints and matches use,
 * every run of white space one space and none at either end.
 */
final class Text
{
    private function __construct()
    {
    }

    /**
     * The text with every run of white space made one space and trimmed.
     */
    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * The words of an inline HTML text: tags left out, character references decoded, white space normalized.
     */
    public static function plain(string $html): string
    {
        return self::normalize(html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }

    /**
     * Whether the text holds anything but white space and tags.
     */
    public static function hasWords(string $html): bool
    {
        return '' !== self::plain($html);
    }
}
