<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Tenancy;

use Override;
use Phil\ResourcePermissions\Contract\TupleScope;
use Phil\ResourcePermissions\Exception\UnscopedTuples;
use Phil\TenancyBundle\Application\TenantContext;

/**
 * The partition of the tuples is the tenant of `phil/tenancy-bundle`'s context, read and never changed: the one the
 * request, the message or the command acts in. Outside any tenant, the tuples are neither read nor written.
 */
final readonly class TenancyTupleScope implements TupleScope
{
    public function __construct(
        private TenantContext $context,
    ) {
    }

    #[Override]
    public function partition(): string
    {
        return $this->context->current()->value ?? throw UnscopedTuples::because('The access tuples are partitioned by tenant, and no tenant is active: enter one, or give a command its --tenant.');
    }
}
