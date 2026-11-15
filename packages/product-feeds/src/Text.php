<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

use Phil\ProductFeeds\Exception\InvalidFeedItem;

/**
 * The text a feed carries: UTF-8, without the control characters XML 1.0 cannot hold nor the code points U+FFFE and
 * U+FFFF, trimmed, and on one line unless it is a description, which keeps its line breaks.
 *
 * @internal
 */
final class Text
{
    /**
     * Text that holds 1 to the maximum of characters, once cleaned.
     *
     * @param string $field the field's name, for the message of a refusal
     */
    public static function required(string $text, string $field, int $maximum, bool $multiline = false): string
    {
        return self::optional($text, $field, $maximum, $multiline)
            ?? throw InvalidFeedItem::because(\sprintf('The %s holds 1 to %d characters, not blank.', $field, $maximum));
    }

    /**
     * Text that holds the maximum of characters at most, once cleaned; none when it is blank.
     *
     * @param string $field the field's name, for the message of a refusal
     */
    public static function optional(?string $text, string $field, int $maximum, bool $multiline = false): ?string
    {
        if (null === $text) {
            return null;
        }

        if (1 !== preg_match('//u', $text)) {
            throw InvalidFeedItem::because(\sprintf('The %s is not UTF-8.', $field));
        }

        $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{FFFE}\x{FFFF}]/u', '', $text);
        $text = trim((string) preg_replace($multiline ? '/[ \t]*\R/u' : '/\s+/u', $multiline ? "\n" : ' ', $text));

        if ('' === $text) {
            return null;
        }

        if (mb_strlen($text) > $maximum) {
            throw InvalidFeedItem::because(\sprintf('The %s holds %d characters at most.', $field, $maximum));
        }

        return $text;
    }

    /**
     * An identifier: 1 to the maximum of visible ASCII characters.
     */
    public static function identifier(string $identifier, string $field, int $maximum): string
    {
        if (1 !== preg_match('/^[\x21-\x7E]+$/D', $identifier) || \strlen($identifier) > $maximum) {
            throw InvalidFeedItem::because(\sprintf('The %s holds 1 to %d visible ASCII characters.', $field, $maximum));
        }

        return $identifier;
    }
}
