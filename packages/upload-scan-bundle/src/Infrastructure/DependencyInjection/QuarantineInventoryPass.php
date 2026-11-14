<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\UploadScanBundle\Application\Contract\Quarantine;
use Phil\UploadScanBundle\Application\Contract\QuarantineInventory;
use Phil\UploadScanBundle\Application\QuarantinePurger;
use Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController;
use Phil\UploadScanBundle\Infrastructure\Console\PurgeQuarantineCommand;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Gives `QuarantineInventory` the quarantine when the quarantine can be listed, unless the application names its own;
 * without an inventory, the quarantine's administration and its purge are removed.
 */
final readonly class QuarantineInventoryPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(QuarantineInventory::class) && $container->has(Quarantine::class)) {
            $id = Quarantine::class;

            while ($container->hasAlias($id)) {
                $id = (string) $container->getAlias($id);
            }

            // A service registered by its class alone has its identifier for a class.
            $class = $container->hasDefinition($id) ? $container->getParameterBag()->resolveValue($container->getDefinition($id)->getClass() ?? $id) : null;

            if (\is_string($class) && is_a($class, QuarantineInventory::class, true)) {
                $container->setAlias(QuarantineInventory::class, Quarantine::class);
            }
        }

        if (!$container->has(QuarantineInventory::class)) {
            foreach ([QuarantineAdminController::class, QuarantinePurger::class, PurgeQuarantineCommand::class] as $id) {
                $container->removeDefinition($id);
            }
        }
    }
}
