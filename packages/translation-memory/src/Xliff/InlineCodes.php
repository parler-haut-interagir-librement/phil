<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Xliff;

/**
 * The tags of an inline HTML text, which XLIFF carries as inline codes rather than as text a translator could break.
 *
 * @internal
 */
final class InlineCodes
{
    private function __construct()
    {
    }

    /**
     * The text cut into runs of words and tags, in order, each tag whole.
     *
     * @return list<array{bool, string}> each run, and whether it is a tag
     */
    public static function tokens(string $html): array
    {
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, \PREG_SPLIT_DELIM_CAPTURE);
        $tokens = [];

        foreach (false === $parts ? [] : $parts as $index => $part) {
            if ('' !== $part) {
                $tokens[] = [1 === $index % 2, $part];
            }
        }

        return $tokens;
    }

    /**
     * A translated text with only the tags its source holds: any other tag — one an imported file added — is left
     * out, its words kept.
     */
    public static function restrictTo(string $html, string $source): string
    {
        $allowed = array_column(self::tokens($source), 1);
        $kept = '';

        foreach (self::tokens($html) as [$tag, $token]) {
            $kept .= !$tag || \in_array($token, $allowed, true) ? $token : '';
        }

        return $kept;
    }

    /**
     * The words of a run of inline HTML as an XML text node holds them, character references decoded.
     */
    public static function decode(string $text): string
    {
        return html_entity_decode($text, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
    }

    /**
     * Words read from XML, escaped back as inline HTML.
     */
    public static function encode(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
