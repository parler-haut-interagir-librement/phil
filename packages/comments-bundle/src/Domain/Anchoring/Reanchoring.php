<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Anchoring;

use Phil\CommentsBundle\Domain\ValueObject\Anchor;

/**
 * Finds a passage again in texts that have changed since it was anchored. Every place where its exact text occurs is
 * a candidate; the field it was in comes first, then the other fields of its block, then the rest of the document,
 * since a passage moved by an edit usually stays where it was. Among candidates of the same rank, the one whose
 * surroundings match the context kept with the anchor wins, then, in its own field, the one nearest its old offset,
 * elsewhere the first in reading order. When the text occurs nowhere, the passage has disappeared.
 *
 * The choice is deterministic: the same anchor in the same texts always lands on the same place.
 */
final class Reanchoring
{
    /**
     * The bytes read on each side of the place found for its context: 32 characters of at most four bytes, and the
     * three bytes of a character cut at the edge.
     */
    private const int WINDOW = 4 * Anchor::CONTEXT + 3;

    /**
     * The anchor of the passage in the texts given, in reading order; `null` when it is in none of them.
     *
     * @param list<Passage> $passages
     */
    public static function locate(Anchor $anchor, array $passages): ?Anchor
    {
        $best = null;
        $rank = null;
        $bytes = \strlen($anchor->quote);

        foreach ($passages as $order => $passage) {
            $same = $anchor->isIn($passage->block, $passage->field);
            $sameBlock = null !== $anchor->block && $anchor->block === $passage->block;

            foreach (self::occurrences($passage->text, $anchor->quote) as [$offset, $byte]) {
                $candidate = [$same, $sameBlock, self::sharedBefore($anchor->prefix, $passage->text, $byte) + self::sharedAfter($anchor->suffix, $passage->text, $byte + $bytes), -($same ? abs($offset - $anchor->start) : $order)];

                if (null === $rank || $candidate > $rank) {
                    $best = [$passage, $offset, $byte];
                    $rank = $candidate;
                }
            }
        }

        if (null === $best) {
            return null;
        }

        [$passage, $offset, $byte] = $best;
        $from = max(0, $byte - self::WINDOW);

        return Anchor::of(
            $passage->block,
            $passage->field,
            $offset,
            $anchor->quote,
            mb_substr(substr($passage->text, $from, $byte - $from), -Anchor::CONTEXT),
            mb_substr(substr($passage->text, $byte + $bytes, self::WINDOW), 0, Anchor::CONTEXT),
        );
    }

    /**
     * Each place the quote occurs in the text, overlapping ones included: its character offset and its byte offset.
     * The text is searched by bytes and its characters counted once, from one place to the next, so that a text where
     * the quote occurs everywhere costs a time bounded by its length.
     *
     * @return iterable<array{int, int}>
     */
    private static function occurrences(string $text, string $quote): iterable
    {
        $counted = 0;
        $offset = 0;
        $found = -1;

        while (false !== ($found = strpos($text, $quote, $found + 1))) {
            $offset += mb_strlen(substr($text, $counted, $found - $counted));
            $counted = $found;

            yield [$offset, $found];
        }
    }

    /**
     * How many characters the context kept before a passage shares with the text before the place found, read from
     * their ends, byte by byte; a character only partly shared does not count.
     */
    private static function sharedBefore(string $kept, string $text, int $at): int
    {
        $length = min(\strlen($kept), $at);
        $count = 0;

        while ($count < $length && $kept[-1 - $count] === $text[$at - 1 - $count]) {
            ++$count;
        }

        while ($count > 0 && 0x80 === (\ord($kept[-$count]) & 0xC0)) {
            --$count;
        }

        return 0 === $count ? 0 : mb_strlen(substr($kept, -$count));
    }

    /**
     * How many characters the context kept after a passage shares with the text after the place found, read from
     * their starts, byte by byte; a character only partly shared does not count.
     */
    private static function sharedAfter(string $kept, string $text, int $at): int
    {
        $length = min(\strlen($kept), \strlen($text) - $at);
        $count = 0;

        while ($count < $length && $kept[$count] === $text[$at + $count]) {
            ++$count;
        }

        // The context is valid UTF-8: its first byte starts a character, so the loop stops there at the latest.
        while ($count < \strlen($kept) && 0x80 === (\ord($kept[$count]) & 0xC0)) {
            --$count;
        }

        return mb_strlen(substr($kept, 0, $count));
    }
}
