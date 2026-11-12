<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Workflow;

use Override;
use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Application\Publication\PublicationWorkflow;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;

/**
 * Adds the publication workflow to the container's `workflow.registry`, for `Publication` subjects: whoever asks the
 * registry for the workflow of a publication gets it. Without a registry, the workflow stays a service of its own.
 */
final class PublicationWorkflowPass implements CompilerPassInterface
{
    public const string REGISTRY = 'workflow.registry';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::REGISTRY) || !$container->hasDefinition(PublicationWorkflow::SERVICE)) {
            return;
        }

        $container->getDefinition(self::REGISTRY)->addMethodCall('addWorkflow', [
            new Reference(PublicationWorkflow::SERVICE),
            new Definition(InstanceOfSupportStrategy::class, [Publication::class]),
        ]);
    }
}
