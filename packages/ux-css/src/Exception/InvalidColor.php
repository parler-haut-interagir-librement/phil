<?php

declare(strict_types=1);

namespace Phil\UxCss\Exception;

use InvalidArgumentException;

/**
 * A colour that is not written as `oklch(L C H)` or `oklch(L C H / A)`.
 */
final class InvalidColor extends InvalidArgumentException implements UxCssException
{
    public static function of(string $value): self
    {
        return new self(\sprintf('"%s" is not an OKLCH colour: write "oklch(L C H)", the lightness as a number from 0 to 1 or a percentage.', $value));
    }
}
