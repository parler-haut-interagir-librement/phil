<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Infrastructure;

use Phil\CommerceBundle\Application\OrderWorkflow;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Infrastructure\Workflow\OrderWorkflowPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;

#[CoversClass(OrderWorkflowPass::class)]
final class OrderWorkflowPassTest extends TestCase
{
    public function testTheWorkflowJoinsTheRegistryForOrders(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(OrderWorkflowPass::REGISTRY, new Definition(Registry::class));
        $container->setDefinition(OrderWorkflow::SERVICE, new Definition(StateMachine::class));

        new OrderWorkflowPass()->process($container);

        self::assertEquals(
            [['addWorkflow', [new Reference(OrderWorkflow::SERVICE), new Definition(InstanceOfSupportStrategy::class, [Order::class])]]],
            $container->getDefinition(OrderWorkflowPass::REGISTRY)->getMethodCalls(),
        );
    }

    public function testWithoutARegistryOrTheWorkflowNothingChanges(): void
    {
        $withoutRegistry = new ContainerBuilder();
        $withoutRegistry->setDefinition(OrderWorkflow::SERVICE, new Definition(StateMachine::class));
        new OrderWorkflowPass()->process($withoutRegistry);
        self::assertFalse($withoutRegistry->hasDefinition(OrderWorkflowPass::REGISTRY));

        $withoutWorkflow = new ContainerBuilder();
        $withoutWorkflow->setDefinition(OrderWorkflowPass::REGISTRY, new Definition(Registry::class));
        new OrderWorkflowPass()->process($withoutWorkflow);
        self::assertSame([], $withoutWorkflow->getDefinition(OrderWorkflowPass::REGISTRY)->getMethodCalls());
    }
}
