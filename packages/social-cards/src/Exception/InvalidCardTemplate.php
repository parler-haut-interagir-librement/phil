<?php

declare(strict_types=1);

namespace Phil\SocialCards\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A card template, or the content of a card, that cannot be drawn: a colour, a size or a bound out of range, a font
 * that is not a TrueType or OpenType file, an image that is not one.
 */
final class InvalidCardTemplate extends InvalidArgumentException implements SocialCardsException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
