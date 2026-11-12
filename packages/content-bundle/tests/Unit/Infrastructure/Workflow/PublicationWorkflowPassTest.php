<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Infrastructure\Workflow;

use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Application\Publication\PublicationWorkflow;
use Phil\ContentBundle\Infrastructure\Workflow\PublicationWorkflowPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Registry;
use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;

#[CoversClass(PublicationWorkflowPass::class)]
final class PublicationWorkflowPassTest extends TestCase
{
    public function testTheRegistryGetsTheWorkflowForPublications(): void
    {
        $container = new ContainerBuilder();
        $container->register('workflow.registry', Registry::class);
        $container->setAlias(Registry::class, 'workflow.registry');
        $container->register(PublicationWorkflow::SERVICE, StateMachine::class);

        new PublicationWorkflowPass()->process($container);

        self::assertEquals(
            [['addWorkflow', [new Reference(PublicationWorkflow::SERVICE), new Definition(InstanceOfSupportStrategy::class, [Publication::class])]]],
            $container->getDefinition('workflow.registry')->getMethodCalls(),
        );
    }

    public function testWithoutARegistryOrTheWorkflowNothingIsAdded(): void
    {
        $alone = new ContainerBuilder();
        $alone->register(PublicationWorkflow::SERVICE, StateMachine::class);
        new PublicationWorkflowPass()->process($alone);
        self::assertFalse($alone->has('workflow.registry'));

        $registryOnly = new ContainerBuilder();
        $registryOnly->register('workflow.registry', Registry::class);
        new PublicationWorkflowPass()->process($registryOnly);
        self::assertSame([], $registryOnly->getDefinition('workflow.registry')->getMethodCalls());
    }
}
