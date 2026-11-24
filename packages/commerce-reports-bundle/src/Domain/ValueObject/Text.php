<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\ValueObject;

use Phil\CommerceReportsBundle\Domain\Exception\InvalidFigure;

/**
 * The checks every text of a line shares: a SKU, a product's name, a category, a channel, a location, a cashier.
 *
 * @internal
 */
final readonly class Text
{
    /** The longest text, the longest label of a chart or a report's parameter. */
    public const int MAX = 200;

    /**
     * @throws InvalidFigure when the text is empty or blank, or longer than 200 characters
     */
    public static function required(string $what, string $text): string
    {
        if ('' === trim($text)) {
            throw InvalidFigure::because(\sprintf('a %s is not empty', $what));
        }

        if (mb_strlen($text) > self::MAX) {
            throw InvalidFigure::because(\sprintf('a %s is at most %d characters long', $what, self::MAX));
        }

        return $text;
    }

    /**
     * @throws InvalidFigure when the text is given but empty or blank, or longer than 200 characters
     */
    public static function optional(string $what, ?string $text): ?string
    {
        return null === $text ? null : self::required($what, $text);
    }
}
