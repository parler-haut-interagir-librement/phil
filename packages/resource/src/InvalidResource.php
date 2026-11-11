<?php

declare(strict_types=1);

namespace Phil\Resource;

use InvalidArgumentException;

/**
 * A resource reference against the rules.
 */
final class InvalidResource extends InvalidArgumentException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
