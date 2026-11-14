<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Contract\TenantScope;
use Phil\AuditBundle\Infrastructure\Security\SecurityActorResolver;
use Phil\AuditBundle\Infrastructure\Tenancy\TenancyTenantScope;
use Phil\TenancyBundle\Application\TenantContext;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Keeps the adapters of the context only where what they read exists: the actor of the Security component with its
 * token storage, the tenant of `phil/tenancy-bundle` with its context. An extension cannot see the other bundles'
 * services when it loads; this pass runs once they are all registered, and removes an adapter, with its alias, that
 * would read nothing.
 */
final class ContextPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        $this->keepWhen($container, 'security.token_storage', SecurityActorResolver::class, ActorResolver::class);
        $this->keepWhen($container, TenantContext::class, TenancyTenantScope::class, TenantScope::class);
    }

    private function keepWhen(ContainerBuilder $container, string $needed, string $adapter, string $port): void
    {
        if (!$container->hasDefinition($adapter) || $container->has($needed)) {
            return;
        }

        $container->removeDefinition($adapter);

        if ($container->hasAlias($port) && $adapter === (string) $container->getAlias($port)) {
            $container->removeAlias($port);
        }
    }
}
