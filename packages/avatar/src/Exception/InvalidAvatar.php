<?php

declare(strict_types=1);

namespace Phil\Avatar\Exception;

use InvalidArgumentException;

/**
 * An avatar that cannot be drawn: a seed, a color, a palette, a size or a style out of bounds.
 */
final class InvalidAvatar extends InvalidArgumentException implements AvatarException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
