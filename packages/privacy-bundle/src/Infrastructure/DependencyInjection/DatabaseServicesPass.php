<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\PrivacyBundle\Infrastructure\Console\AnonymizeDatabaseCommand;
use Phil\PrivacyBundle\Infrastructure\Database\DbalAnonymizationTarget;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Removes the database services when the connection they were given is not a service of the application, as in an
 * application without DoctrineBundle: the anonymize command is then absent rather than a container that does not
 * compile.
 */
final class DatabaseServicesPass implements CompilerPassInterface
{
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AnonymizationServices::DATABASE_IDENTITY)) {
            return;
        }

        $connection = $container->getDefinition(AnonymizationServices::DATABASE_IDENTITY)->getArgument(0);

        if ($connection instanceof Reference && $container->has((string) $connection)) {
            return;
        }

        foreach ([AnonymizationServices::DATABASE_IDENTITY, DbalAnonymizationTarget::class, AnonymizeDatabaseCommand::class] as $id) {
            $container->removeDefinition($id);
        }
    }
}
