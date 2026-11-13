<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\Checkin\Offline\OfflineSync;
use Phil\CheckinBundle\Infrastructure\Catalog\CredentialCatalogs;
use Phil\CheckinBundle\Infrastructure\Http\CheckinController;
use Phil\CheckinBundle\Infrastructure\Http\DeskConfig;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointDesks;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Turns the offline services off when the configuration names no catalog and no service is tagged
 * {@see CheckinServices::CATALOG_TAG}: the desks then check in online only, and the manifest and synchronisation
 * endpoints answer 404. It runs after autoconfiguration has tagged the application's catalogs.
 */
final readonly class CredentialCatalogPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CredentialCatalogs::class) || [] !== $container->findTaggedServiceIds(CheckinServices::CATALOG_TAG)) {
            return;
        }

        $container->removeDefinition(CredentialCatalogs::class);
        $container->removeDefinition(OfflineSync::class);
        $container->getDefinition(CheckpointDesks::class)->replaceArgument(5, null);
        $container->getDefinition(DeskConfig::class)->replaceArgument(2, false);
        $container->getDefinition(CheckinController::class)->replaceArgument(3, null);
    }
}
