<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use RuntimeException;

/**
 * The tenant cannot be served for now: its database failed its last migration, or the tenant is being moved to
 * another database. A request is answered with a `503`, and a message is retried later.
 */
final class TenantDatabaseUnavailable extends RuntimeException implements DomainError
{
    public static function suspendedTenant(TenantId $tenant): self
    {
        return new self(\sprintf('The tenant "%s" is being moved to another database.', $tenant->value));
    }

    public static function suspendedDatabase(TenantId $tenant, Placement $placement): self
    {
        return new self(\sprintf('The database "%s" of the tenant "%s" failed its last migration.', $placement->toString(), $tenant->value));
    }
}
