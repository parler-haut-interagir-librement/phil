<?php

declare(strict_types=1);

namespace Phil\Stockroom;

use Phil\Stockroom\Exception\InvalidStock;

/**
 * The names and notes the library keeps: trimmed, valid UTF-8, without control characters, within a length.
 */
final class Text
{
    /** The longest name, in characters. */
    public const int NAME = 200;

    /** The longest note, in characters. */
    public const int NOTE = 1000;

    private function __construct()
    {
    }

    /**
     * The text trimmed, refused when it is empty, too long, not UTF-8 or holds a control character.
     *
     * @throws InvalidStock when it is not a text the library keeps
     */
    public static function check(string $value, string $what, int $max = self::NAME): string
    {
        $text = trim($value);

        if ('' === $text || !mb_check_encoding($text, 'UTF-8') || 1 === preg_match('/\p{Cc}/u', $text) || mb_strlen($text) > $max) {
            throw InvalidStock::because(\sprintf('The %s is a text of 1 to %d characters, without control characters.', $what, $max));
        }

        return $text;
    }

    /**
     * @throws InvalidStock when it is given and not a text the library keeps
     */
    public static function optional(?string $value, string $what, int $max = self::NOTE): ?string
    {
        return null === $value ? null : self::check($value, $what, $max);
    }
}
