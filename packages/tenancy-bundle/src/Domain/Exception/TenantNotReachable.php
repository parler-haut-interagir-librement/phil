<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * A switch to a tenant the account cannot act in. The request is refused; it is never narrowed down to a
 * tenant that would be reachable.
 */
final class TenantNotReachable extends DomainException implements DomainError
{
    public static function forTenant(TenantId $tenant): self
    {
        return new self(\sprintf('The tenant "%s" is not reachable by this account.', $tenant->value));
    }
}
