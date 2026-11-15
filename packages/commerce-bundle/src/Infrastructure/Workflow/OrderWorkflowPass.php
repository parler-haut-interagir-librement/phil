<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Infrastructure\Workflow;

use Override;
use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Domain\Model\Order;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;

/**
 * Adds the order workflow to the container's `workflow.registry`, for `Order` subjects: whoever asks the registry for
 * the workflow of an order gets it, and `workflow:dump phil_commerce_order` draws it. Without a registry, the
 * workflow stays a service of its own.
 */
final class OrderWorkflowPass implements CompilerPassInterface
{
    public const string REGISTRY = 'workflow.registry';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::REGISTRY) || !$container->hasDefinition(OrderWorkflow::SERVICE)) {
            return;
        }

        $container->getDefinition(self::REGISTRY)->addMethodCall('addWorkflow', [
            new Reference(OrderWorkflow::SERVICE),
            new Definition(InstanceOfSupportStrategy::class, [Order::class]),
        ]);
    }
}
