<?php

declare(strict_types=1);

namespace Phil\ReservationBundle;

use Override;
use Phil\CalendarBundle\Application\View\ItemSource;
use Phil\ReservationBundle\Application\Contract\ReminderSender;
use Phil\ReservationBundle\Application\Reminders;
use Phil\ReservationBundle\Application\Reservations;
use Phil\ReservationBundle\Infrastructure\Calendar\ReservationItems;
use Phil\ReservationBundle\Infrastructure\Console\ExpireHoldsCommand;
use Phil\ReservationBundle\Infrastructure\Console\SendRemindersCommand;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineReservationRepository;
use Phil\ReservationBundle\Infrastructure\Doctrine\DoctrineResourceRepository;
use Phil\ReservationBundle\Infrastructure\Form\ReservationType;
use Phil\ReservationBundle\Infrastructure\Messenger\MessengerReminderSender;
use Phil\ReservationBundle\Infrastructure\Translation\ViolationMessages;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Reservations of resources: both in Doctrine, the service that holds, books, confirms, moves and cancels them under
 * each resource's lock, the reservation form with the reasons in the reader's language, the commands that expire
 * holds and send reminders through Messenger, and the confirmed reservations in `phil/calendar-bundle`'s views.
 */
final class PhilReservationBundle extends AbstractBundle
{
    /** The tag of `phil/calendar-bundle`'s item sources. */
    public const string CALENDAR_ITEM_SOURCE = 'phil_calendar.item_source';

    #[Override]
    protected string $extensionAlias = 'phil_reservation';

    /**
     * The reminders go out through Messenger's bus when there is one, or through the application's own
     * `ReminderSender`; without either, there is no reminder service nor command.
     */
    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $sender = $container->hasAlias(ReminderSender::class) ? (string) $container->getAlias(ReminderSender::class) : null;

                if (MessengerReminderSender::class === $sender && !$container->has(MessageBusInterface::class)) {
                    $container->removeAlias(ReminderSender::class);
                }

                if (!$container->has(ReminderSender::class)) {
                    $container->removeDefinition(Reminders::class);
                    $container->removeDefinition(SendRemindersCommand::class);
                }

                if (!$container->has(MessageBusInterface::class)) {
                    $container->removeDefinition(MessengerReminderSender::class);
                }
            }
        });
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('batch')
                    ->info('The most reservations each run of the commands reads, and the calendar shows in a period.')
                    ->defaultValue(500)
                    ->min(1)
                    ->max(10_000)
                ->end()
                ->booleanNode('calendar')
                    ->info("Whether the confirmed reservations are items of phil/calendar-bundle's views, when it is installed.")
                    ->defaultTrue()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine')) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilReservationBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\ReservationBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        // The ports by reflection: the bundle class wires adapters, and names no class of the domain.
        foreach ([DoctrineResourceRepository::class, DoctrineReservationRepository::class] as $adapter) {
            $services->set($adapter)->args([new Reference('doctrine.orm.entity_manager')]);

            foreach (new ReflectionClass($adapter)->getInterfaceNames() as $port) {
                $services->alias($port, $adapter)->public();
            }
        }

        $services->set(Reservations::class)
            ->args([
                new Reference(DoctrineResourceRepository::class),
                new Reference(DoctrineReservationRepository::class),
                new Reference('clock'),
                new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->public();
        $services->set(ViolationMessages::class)->args([new Reference('translator')])->public();
        $services->set(ReservationType::class)
            ->args([new Reference(Reservations::class), new Reference(ViolationMessages::class), new Reference('translator')])
            ->tag('form.type');

        if (interface_exists(MessageBusInterface::class)) {
            $services->set(MessengerReminderSender::class)->args([new Reference(MessageBusInterface::class)]);
            $services->alias(ReminderSender::class, MessengerReminderSender::class);
        }

        $services->set(Reminders::class)
            ->args([new Reference(DoctrineResourceRepository::class), new Reference(DoctrineReservationRepository::class), new Reference(ReminderSender::class), new Reference('clock')])
            ->public();

        if (class_exists(Command::class)) {
            $services->set(ExpireHoldsCommand::class)->args([new Reference(Reservations::class), $config['batch']])->tag('console.command');
            $services->set(SendRemindersCommand::class)->args([new Reference(Reminders::class), $config['batch']])->tag('console.command');
        }

        if (true === $config['calendar'] && interface_exists(ItemSource::class)) {
            $services->set(ReservationItems::class)
                ->args([new Reference(DoctrineReservationRepository::class), $config['batch']])
                ->tag(self::CALENDAR_ITEM_SOURCE);
        }
    }
}
