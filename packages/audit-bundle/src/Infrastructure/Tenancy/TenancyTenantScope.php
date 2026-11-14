<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\Tenancy;

use Override;
use Phil\AuditBundle\Application\Contract\TenantScope;
use Phil\TenancyBundle\Application\TenantContext;

/**
 * The tenant of `phil/tenancy-bundle`'s context, read and never changed: the one the request, the message or the
 * command acts in.
 */
final readonly class TenancyTenantScope implements TenantScope
{
    public function __construct(
        private TenantContext $context,
    ) {
    }

    #[Override]
    public function current(): ?string
    {
        return $this->context->current()?->value;
    }
}
