<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * No active partner is known by that code.
 */
final class UnknownPartner extends DomainException implements DomainError
{
    public static function code(string $code): self
    {
        return new self(\sprintf('No active partner is known as "%s".', $code));
    }
}
