<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\OrganizationsBundle\Application\Contract\UnitStore;
use Phil\OrganizationsBundle\Application\UnitAccess;
use Phil\OrganizationsBundle\Application\Units;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Infrastructure\Http\UnitAdminController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Leaves out what the application cannot run:
 *
 * - the units, when its store keeps none — a store of its own that implements `OrganizationStore` and not
 *   `UnitStore` —; the services that read the units only when there are some — the members, the erasure, the privacy
 *   and the voter — then go without;
 * - the administration pages of the units, without Twig, the router or the CSRF token manager.
 */
final readonly class UnitServicesPass implements CompilerPassInterface
{
    public const array UNIT_SERVICES = [UnitAccess::class, Units::class, UnitTree::class, UnitAdminController::class];

    public const array PAGE_SERVICES = ['twig', 'router', 'security.csrf.token_manager'];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasAlias(UnitStore::class) && !$this->keepsUnits($container, (string) $container->getAlias(UnitStore::class))) {
            $container->removeAlias(UnitStore::class);

            foreach (self::UNIT_SERVICES as $id) {
                $container->removeDefinition($id);
            }
        }

        foreach (self::PAGE_SERVICES as $id) {
            if (!$container->has($id)) {
                $container->removeDefinition(UnitAdminController::class);
            }
        }
    }

    private function keepsUnits(ContainerBuilder $container, string $store): bool
    {
        if (!$container->has($store)) {
            return false;
        }

        $class = $container->getParameterBag()->resolveValue($container->findDefinition($store)->getClass() ?? $store);

        return \is_string($class) && is_subclass_of($class, UnitStore::class);
    }
}
