<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * No open channel answers on a domain, or is known by a code.
 */
final class UnknownChannel extends DomainException implements DomainError
{
    public static function host(string $host): self
    {
        return new self(\sprintf('No open channel answers on "%s".', $host));
    }

    public static function code(string $code): self
    {
        return new self(\sprintf('No open channel is known as "%s".', $code));
    }
}
