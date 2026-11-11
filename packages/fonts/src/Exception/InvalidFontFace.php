<?php

declare(strict_types=1);

namespace Phil\Fonts\Exception;

use InvalidArgumentException;

/**
 * An `@font-face` rule that cannot be written: no family, no source.
 */
final class InvalidFontFace extends InvalidArgumentException implements FontsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
