<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use Phil\ScheduledTransitionsBundle\Application\TransitionHandler;
use Phil\ScheduledTransitionsBundle\Domain\Repository\ScheduledTransitionRepository;
use Phil\ScheduledTransitionsBundle\Infrastructure\Console\RunTransitionsCommand;
use Phil\ScheduledTransitionsBundle\Infrastructure\Doctrine\DoctrineScheduledTransitionRepository;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\RunDueTransitions;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\RunDueTransitionsHandler;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\TransitionsSchedule;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\SubjectResolver;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\WorkflowTransitionHandler;
use Phil\ScheduledTransitionsBundle\PhilScheduledTransitionsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilScheduledTransitionsBundle::class)]
final class PhilScheduledTransitionsBundleTest extends TestCase
{
    public function testTheServicesFollowTheConfiguration(): void
    {
        $container = $this->load([['frequency' => '5 minutes', 'batch' => 20, 'max_attempts' => 5, 'claim_for' => 120]]);

        self::assertSame('phil_scheduled_transitions', $this->extension()->getAlias());
        self::assertSame(['service_container', DoctrineScheduledTransitionRepository::class, Scheduler::class, WorkflowTransitionHandler::class, TransitionsSchedule::class, RunDueTransitionsHandler::class, RunTransitionsCommand::class], array_keys($container->getDefinitions()));
        self::assertEquals([new Reference('doctrine.orm.entity_manager')], $container->getDefinition(DoctrineScheduledTransitionRepository::class)->getArguments());
        self::assertSame(DoctrineScheduledTransitionRepository::class, (string) $container->getAlias(ScheduledTransitionRepository::class));
        self::assertTrue($container->getAlias(ScheduledTransitionRepository::class)->isPublic());
        self::assertEquals([new Reference(ScheduledTransitionRepository::class), new TaggedIteratorArgument('phil_scheduled_transitions.handler'), new Reference('clock'), 5, 120], $container->getDefinition(Scheduler::class)->getArguments());
        self::assertTrue($container->getDefinition(Scheduler::class)->isPublic());
        self::assertEquals([new Reference('workflow.registry', ContainerInterface::NULL_ON_INVALID_REFERENCE), new TaggedIteratorArgument('phil_scheduled_transitions.subject_resolver')], $container->getDefinition(WorkflowTransitionHandler::class)->getArguments());
        self::assertTrue($container->getDefinition(WorkflowTransitionHandler::class)->hasTag('phil_scheduled_transitions.handler'));
        self::assertSame(['5 minutes', 20], $container->getDefinition(TransitionsSchedule::class)->getArguments());
        self::assertSame([['name' => 'phil_transitions']], $container->getDefinition(TransitionsSchedule::class)->getTag('scheduler.schedule_provider'));
        self::assertEquals([new Reference(Scheduler::class)], $container->getDefinition(RunDueTransitionsHandler::class)->getArguments());
        self::assertSame([['handles' => RunDueTransitions::class]], $container->getDefinition(RunDueTransitionsHandler::class)->getTag('messenger.message_handler'));
        self::assertEquals([new Reference(Scheduler::class)], $container->getDefinition(RunTransitionsCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(RunTransitionsCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getAutoconfiguredInstanceof()[TransitionHandler::class]->hasTag(PhilScheduledTransitionsBundle::HANDLER_TAG));
        self::assertTrue($container->getAutoconfiguredInstanceof()[SubjectResolver::class]->hasTag(PhilScheduledTransitionsBundle::RESOLVER_TAG));

        self::assertSame([1, 1], [$this->load([['batch' => 1]])->getDefinition(TransitionsSchedule::class)->getArgument(1), $this->load([['max_attempts' => 1]])->getDefinition(Scheduler::class)->getArgument(3)]);
        $defaults = $this->load([]);
        self::assertSame(['1 minute', 100], $defaults->getDefinition(TransitionsSchedule::class)->getArguments());
        self::assertSame([3, 600], [$defaults->getDefinition(Scheduler::class)->getArgument(3), $defaults->getDefinition(Scheduler::class)->getArgument(4)]);
    }

    public function testBadSettingsAreRefused(): void
    {
        foreach ([['batch' => 0], ['max_attempts' => 0], ['claim_for' => 0], ['frequency' => '']] as $config) {
            try {
                $this->load([$config]);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }

        $least = $this->load([['batch' => 1, 'max_attempts' => 1, 'claim_for' => 1]]);
        self::assertSame([1, 1], [$least->getDefinition(Scheduler::class)->getArgument(3), $least->getDefinition(Scheduler::class)->getArgument(4)]);
    }

    public function testTheEntityIsMappedWithDoctrine(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        /** @var array{orm: array{mappings: array<string, array<string, mixed>>}} $doctrine */
        $doctrine = $container->getExtensionConfig('doctrine')[0];
        $mapping = $doctrine['orm']['mappings']['PhilScheduledTransitionsBundle'];
        self::assertSame(['attribute', false, 'Phil\ScheduledTransitionsBundle\Domain\Model'], [$mapping['type'] ?? null, $mapping['is_bundle'] ?? null, $mapping['prefix'] ?? null]);
        self::assertIsString($mapping['dir'] ?? null);
        self::assertStringEndsWith('/src/Domain/Model', $mapping['dir']);

        $without = new ContainerBuilder();
        $without->registerExtension($extension);

        $extension->prepend($without);
        self::assertFalse($without->hasExtension('doctrine'));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilScheduledTransitionsBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
