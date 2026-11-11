<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A resource, a part, a holder or a duration that a lease cannot be made of.
 */
final class InvalidLease extends InvalidArgumentException implements EditLockException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
