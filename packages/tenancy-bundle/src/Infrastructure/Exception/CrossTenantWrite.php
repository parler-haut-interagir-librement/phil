<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Exception;

use RuntimeException;

/**
 * A flush that would write a tenant-scoped entity of another tenant than the active one: an insertion, an update or
 * a deletion. The filter guards reads only; this refusal keeps a unit of work in one tenant from writing into
 * another. Work that legitimately spans tenants disables the filter, or runs in no tenant.
 */
final class CrossTenantWrite extends RuntimeException
{
    public static function of(string $entity, ?string $tenant, string $active): self
    {
        return new self(\sprintf('The entity "%s" belongs to %s, not to the active tenant "%s": the flush is refused.', $entity, null === $tenant ? 'no tenant' : \sprintf('the tenant "%s"', $tenant), $active));
    }
}
