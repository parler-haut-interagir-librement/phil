<?php

declare(strict_types=1);

namespace Phil\ScheduledTransitionsBundle;

use Override;
use Phil\ScheduledTransitionsBundle\Application\Scheduler;
use Phil\ScheduledTransitionsBundle\Application\TransitionHandler;
use Phil\ScheduledTransitionsBundle\Infrastructure\Console\RunTransitionsCommand;
use Phil\ScheduledTransitionsBundle\Infrastructure\Doctrine\DoctrineScheduledTransitionRepository;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\RunDueTransitions;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\RunDueTransitionsHandler;
use Phil\ScheduledTransitionsBundle\Infrastructure\Scheduler\TransitionsSchedule;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\SubjectResolver;
use Phil\ScheduledTransitionsBundle\Infrastructure\Workflow\WorkflowTransitionHandler;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Workflow\Registry;

/**
 * Transitions planned for a moment — publish on, unpublish on —, kept in Doctrine and applied by their handlers or by
 * `symfony/workflow`, from Symfony Scheduler or a command.
 */
final class PhilScheduledTransitionsBundle extends AbstractBundle
{
    public const string HANDLER_TAG = 'phil_scheduled_transitions.handler';

    public const string RESOLVER_TAG = 'phil_scheduled_transitions.subject_resolver';

    #[Override]
    protected string $extensionAlias = 'phil_scheduled_transitions';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('frequency')
                    ->info('How often Symfony Scheduler applies the transitions that are due.')
                    ->defaultValue('1 minute')
                    ->cannotBeEmpty()
                ->end()
                ->integerNode('batch')
                    ->info('How many transitions a run applies at most.')
                    ->min(1)
                    ->defaultValue(100)
                ->end()
                ->integerNode('max_attempts')
                    ->info('How many times a failing transition is tried before it is given up.')
                    ->min(1)
                    ->defaultValue(3)
                ->end()
                ->integerNode('claim_for')
                    ->info('How many seconds a run holds a transition it applies; longer than any handler takes. Another run takes it back once this lapses.')
                    ->min(1)
                    ->defaultValue(600)
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilScheduledTransitionsBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\ScheduledTransitionsBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(TransitionHandler::class)->addTag(self::HANDLER_TAG);
        $builder->registerForAutoconfiguration(SubjectResolver::class)->addTag(self::RESOLVER_TAG);
        $services = $container->services();

        // The port by reflection: the bundle class wires the adapter, and names no class of the domain.
        $services->set(DoctrineScheduledTransitionRepository::class)->args([new Reference('doctrine.orm.entity_manager')]);
        $port = new ReflectionClass(DoctrineScheduledTransitionRepository::class)->getInterfaceNames()[0];
        $services->alias($port, DoctrineScheduledTransitionRepository::class)->public();

        $services->set(Scheduler::class)
            ->args([new Reference($port), new TaggedIteratorArgument(self::HANDLER_TAG), new Reference('clock'), $config['max_attempts'], $config['claim_for']])
            ->public();

        if (class_exists(Registry::class)) {
            $services->set(WorkflowTransitionHandler::class)
                ->args([new Reference('workflow.registry', ContainerInterface::NULL_ON_INVALID_REFERENCE), new TaggedIteratorArgument(self::RESOLVER_TAG)])
                ->tag(self::HANDLER_TAG);
        }

        if (class_exists(RecurringMessage::class)) {
            $services->set(TransitionsSchedule::class)
                ->args([$config['frequency'], $config['batch']])
                ->tag('scheduler.schedule_provider', ['name' => TransitionsSchedule::NAME]);
            $services->set(RunDueTransitionsHandler::class)
                ->args([new Reference(Scheduler::class)])
                ->tag('messenger.message_handler', ['handles' => RunDueTransitions::class]);
        }

        if (class_exists(Command::class)) {
            $services->set(RunTransitionsCommand::class)->args([new Reference(Scheduler::class)])->tag('console.command');
        }
    }
}
