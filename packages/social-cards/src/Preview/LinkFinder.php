<?php

declare(strict_types=1);

namespace Phil\SocialCards\Preview;

/**
 * Finds the links a member wrote or pasted in a plain text, in the order they appear, each once: an address that
 * starts with `http://` or `https://` and runs to the next blank, quote or angle bracket. The punctuation that ends a
 * sentence after it — a full stop, a comma, a closing guillemet or quotation mark — is not part of it, nor is a closing
 * parenthesis or bracket it did not open: `(see https://example.org/a_(b))` gives `https://example.org/a_(b)`.
 *
 * The scan is bounded: the first `MAX_TEXT` bytes of the text are read, and it stops at `$max` links. It checks only
 * the shape of an address; whoever fetches one checks it as a whole.
 */
final class LinkFinder
{
    /** The bytes of a text read; a link that runs past them is left out. */
    public const int MAX_TEXT = 65_536;

    /** The longest link kept, as the sitemap protocol and most crawlers allow. */
    public const int MAX_LINK = 2048;

    /** The marks of one byte a link does not end with, unless it opened the parenthesis or the bracket. */
    private const array ASCII_MARKS = ['.' => true, ',' => true, ';' => true, ':' => true, '!' => true, '?' => true, "'" => true, ')' => true, ']' => true];

    /** The marks of several bytes a link does not end with. */
    private const array MARKS = ['»', '”', '’', '›', '…'];

    /**
     * @return list<string>
     */
    public static function find(string $text, int $max = 10): array
    {
        if ($max < 1) {
            return [];
        }

        $text = mb_strcut(mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'UTF-8'), 0, self::MAX_TEXT, 'UTF-8');
        preg_match_all('~(?<![\w/.@-])https?://[^\s<>"`{}|\\\\^\x00-\x1F\x7F]+~iu', $text, $matches);
        $links = [];

        foreach ($matches[0] as $candidate) {
            $link = self::trimmed($candidate);

            if (\strlen($link) > self::MAX_LINK || 1 !== preg_match('~^https?://[^/?#.\s]~i', $link) || \in_array($link, $links, true)) {
                continue;
            }

            $links[] = $link;

            if (\count($links) >= $max) {
                break;
            }
        }

        return $links;
    }

    public static function first(string $text): ?string
    {
        return self::find($text, 1)[0] ?? null;
    }

    /**
     * The link without the punctuation that follows it: the marks that end a sentence, and the closing parentheses
     * and brackets it did not open. Each mark costs the same whatever the length of the link.
     */
    private static function trimmed(string $link): string
    {
        $unopened = [')' => substr_count($link, ')') - substr_count($link, '('), ']' => substr_count($link, ']') - substr_count($link, '[')];
        $end = \strlen($link);

        // A link starts with `http`: no trimming empties it.
        while (null !== $mark = self::markBefore($link, $end)) {
            if (isset($unopened[$mark])) {
                if ($unopened[$mark] < 1) {
                    break;
                }

                --$unopened[$mark];
            }

            $end -= \strlen($mark);
        }

        return substr($link, 0, $end);
    }

    /**
     * The mark the link ends with when it is cut at `$end` bytes: one byte looked up, or a mark of several bytes when
     * that byte is not ASCII.
     */
    private static function markBefore(string $link, int $end): ?string
    {
        $byte = $link[$end - 1];

        if (isset(self::ASCII_MARKS[$byte])) {
            return $byte;
        }

        foreach (self::MARKS as $mark) {
            if (str_ends_with(substr($link, $end - 4, 4), $mark)) {
                return $mark;
            }
        }

        return null;
    }
}
