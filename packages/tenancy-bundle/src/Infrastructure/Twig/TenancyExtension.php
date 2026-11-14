<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Twig;

use Phil\TenancyBundle\Application\Contract\ReachableTenantsProvider;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Twig\Attribute\AsTwigFunction;

/**
 * `active_tenant()`, the reference of the tenant the request acts in or `null`; `reachable_tenants()`, the
 * references the account can switch to, for a selector or a menu.
 */
final readonly class TenancyExtension
{
    public function __construct(
        private TenantContext $context,
        private ReachableTenantsProvider $reachableTenants,
    ) {
    }

    #[AsTwigFunction(name: 'active_tenant')]
    public function activeTenant(): ?string
    {
        return $this->context->current()?->value;
    }

    /**
     * @return list<string>
     */
    #[AsTwigFunction(name: 'reachable_tenants')]
    public function reachable(): array
    {
        return array_map(static fn (TenantId $tenant): string => $tenant->value, $this->reachableTenants->reachable()->toList());
    }
}
