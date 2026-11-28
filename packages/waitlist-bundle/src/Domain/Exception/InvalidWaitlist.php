<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Exception;

use DomainException;

/**
 * A waiting list or an entry given a value it cannot take, or asked for a change its state does not allow.
 */
final class InvalidWaitlist extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
