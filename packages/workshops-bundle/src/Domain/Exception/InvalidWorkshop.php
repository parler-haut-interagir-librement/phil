<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Exception;

use DomainException;

/**
 * A workshop, a session or a registration given a value it cannot take, or asked for a change its state does not
 * allow.
 */
final class InvalidWorkshop extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
