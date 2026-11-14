<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use RuntimeException;
use Throwable;

/**
 * A move of a tenant from one database to another that did not happen: the rows copied to the target were removed,
 * and the tenant is still served from its source.
 */
final class TenantNotMoved extends RuntimeException implements DomainError
{
    public static function placementIsReadOnly(TenantId $tenant): self
    {
        return new self(\sprintf('The tenant "%s" cannot be moved: the TenantPlacement service does not implement PlacementStore.', $tenant->value));
    }

    public static function verificationFailed(TenantId $tenant, Placement $target, string $table): self
    {
        return new self(\sprintf('The tenant "%s" was not moved to "%s": the rows of %s in the target differ from those of the source.', $tenant->value, $target->toString(), $table));
    }

    public static function because(TenantId $tenant, Placement $target, Throwable $previous): self
    {
        return new self(\sprintf('The tenant "%s" was not moved to "%s": %s', $tenant->value, $target->toString(), $previous->getMessage()), 0, $previous);
    }
}
