<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A notification its gateway cannot trust or read: a signature that does not verify, a timestamp too old, a body
 * that is not one of the processor's events. Nothing of it is applied.
 */
final class InvalidNotification extends DomainException implements DomainError
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
