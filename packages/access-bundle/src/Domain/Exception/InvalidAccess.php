<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A member, a level, a group, an entry point or a configuration against the rules.
 */
final class InvalidAccess extends InvalidArgumentException implements AccessException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
