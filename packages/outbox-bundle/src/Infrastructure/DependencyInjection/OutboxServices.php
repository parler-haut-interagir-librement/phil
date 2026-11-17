<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\DependencyInjection;

use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use LogicException;
use Phil\Outbox\Backoff;
use Phil\Outbox\Codecs;
use Phil\Outbox\Contract\EventCodec;
use Phil\Outbox\Contract\Outbox;
use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Housekeeping;
use Phil\Outbox\Memory\InMemoryOutboxStore;
use Phil\Outbox\RecordedEventCodec;
use Phil\Outbox\Relay;
use Phil\Outbox\StoreOutbox;
use Phil\OutboxBundle\Infrastructure\Console\DeadRemoveCommand;
use Phil\OutboxBundle\Infrastructure\Console\DeadRetryCommand;
use Phil\OutboxBundle\Infrastructure\Console\DeadShowCommand;
use Phil\OutboxBundle\Infrastructure\Console\PurgeCommand;
use Phil\OutboxBundle\Infrastructure\Console\RelayCommand;
use Phil\OutboxBundle\Infrastructure\Console\SchemaCommand;
use Phil\OutboxBundle\Infrastructure\Doctrine\DbalOutboxStore;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxSchemaListener;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;
use Phil\OutboxBundle\Infrastructure\Messenger\MessengerDispatcher;
use Phil\OutboxBundle\Infrastructure\Scheduler\OutboxSchedule;
use Phil\OutboxBundle\Infrastructure\Scheduler\RelayOutbox;
use Phil\OutboxBundle\Infrastructure\Scheduler\RelayOutboxHandler;
use Phil\OutboxBundle\Infrastructure\Worker\RelayRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Scheduler\RecurringMessage;

/**
 * The services of the configuration: the store — a table of a DBAL connection, or memory —, the codecs tagged
 * `phil_outbox.codec`, the outbox, the relay and its runner, the housekeeping, the commands when Console is installed,
 * the schema listener when the ORM is, and the scheduled trigger and the Messenger bridge when they are enabled.
 *
 * @phpstan-type Config array{
 *     store: string,
 *     connection: string,
 *     table: non-empty-string,
 *     clock: string,
 *     dispatcher: string,
 *     generic_types: list<string>,
 *     relay: array{batch_size: int, lease: int, max_attempts: int, first_delay: int, longest_delay: int, lock: bool},
 *     retention: int,
 *     scheduler: array{enabled: bool, frequency: int, max_batches: int},
 *     messenger: array{enabled: bool, bus: string},
 * }
 */
final class OutboxServices
{
    /** The `store` that keeps the records in a table of a DBAL connection. */
    public const string STORE_DBAL = 'dbal';

    /** The `store` that keeps the records in memory, for tests and single runs. */
    public const string STORE_MEMORY = 'memory';

    /** The tag of the codecs, added to every service that implements `EventCodec`. */
    public const string CODEC_TAG = 'phil_outbox.codec';

    /** The service that relays the events: the PSR-14 dispatcher of the configuration, or the Messenger bridge. */
    public const string DISPATCHER = 'phil_outbox.dispatcher';

    private function __construct()
    {
    }

    /**
     * @param Config $config
     *
     * @throws LogicException when the scheduled trigger or the Messenger bridge is enabled without its component
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(EventCodec::class)->addTag(self::CODEC_TAG);
        $container->register(OutboxTable::class, OutboxTable::class)->setArguments([$config['table']]);
        $clock = new Reference($config['clock']);
        $connection = self::STORE_DBAL === $config['store'] ? new Reference(\sprintf('doctrine.dbal.%s_connection', $config['connection'])) : null;

        if ($connection instanceof Reference) {
            $container->register(DbalOutboxStore::class, DbalOutboxStore::class)->setArguments([$connection, new Reference(OutboxTable::class)]);
            $container->setAlias(OutboxStore::class, DbalOutboxStore::class);
        } else {
            $container->register(InMemoryOutboxStore::class, InMemoryOutboxStore::class)->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(OutboxStore::class, InMemoryOutboxStore::class);
        }

        if ([] !== $config['generic_types']) {
            $container->register(RecordedEventCodec::class, RecordedEventCodec::class)->setArguments([$config['generic_types']])->addTag(self::CODEC_TAG);
        }

        $store = new Reference(OutboxStore::class);
        $container->register(Codecs::class, Codecs::class)->setArguments([new TaggedIteratorArgument(self::CODEC_TAG)]);
        $container->register(StoreOutbox::class, StoreOutbox::class)->setArguments([$store, new Reference(Codecs::class), $clock]);
        $container->setAlias(Outbox::class, StoreOutbox::class)->setPublic(true);
        self::relay($container, $config, $store, $clock);
        $container->register(Housekeeping::class, Housekeeping::class)->setArguments([$store, $clock])->setPublic(true);

        if ($connection instanceof Reference && class_exists(GenerateSchemaEventArgs::class)) {
            $container->register(OutboxSchemaListener::class, OutboxSchemaListener::class)
                ->setArguments([$connection, new Reference(OutboxTable::class)])
                ->addTag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $config['connection']]);
        }

        if (class_exists(Command::class)) {
            self::commands($container, $config, $connection);
        }

        if ($config['scheduler']['enabled']) {
            self::scheduler($container, $config);
        }
    }

    /**
     * @param Config $config
     */
    private static function relay(ContainerBuilder $container, array $config, Reference $store, Reference $clock): void
    {
        if ($config['messenger']['enabled']) {
            if (!interface_exists(MessageBusInterface::class)) {
                throw new LogicException('The Messenger bridge of the outbox needs symfony/messenger: run "composer require symfony/messenger".');
            }

            $container->register(self::DISPATCHER, MessengerDispatcher::class)->setArguments([new Reference($config['messenger']['bus'])]);
        } else {
            $container->setAlias(self::DISPATCHER, $config['dispatcher']);
        }

        $relay = $config['relay'];
        $container->register(Backoff::class, Backoff::class)->setArguments([$relay['first_delay'], $relay['longest_delay'], $relay['max_attempts']]);
        $container->register(Relay::class, Relay::class)
            ->setArguments([$store, new Reference(Codecs::class), new Reference(self::DISPATCHER), $clock, new Reference(Backoff::class), $relay['lease']])
            ->setPublic(true);
        $container->register(RelayRunner::class, RelayRunner::class)->setArguments([
            new Reference(Relay::class),
            $clock,
            $relay['lock'] ? new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE) : null,
            $relay['lease'],
        ]);
    }

    /**
     * @param Config $config
     */
    private static function commands(ContainerBuilder $container, array $config, ?Reference $connection): void
    {
        $housekeeping = new Reference(Housekeeping::class);
        $container->register(RelayCommand::class, RelayCommand::class)
            ->setArguments([new Reference(RelayRunner::class), $config['relay']['batch_size']])
            ->addTag('console.command', ['command' => 'phil:outbox:relay']);

        foreach ([DeadShowCommand::class, DeadRetryCommand::class, DeadRemoveCommand::class] as $command) {
            $container->register($command, $command)->setArguments([$housekeeping])->addTag('console.command');
        }

        $container->register(PurgeCommand::class, PurgeCommand::class)->setArguments([$housekeeping, $config['retention']])->addTag('console.command');

        if ($connection instanceof Reference) {
            $container->register(SchemaCommand::class, SchemaCommand::class)
                ->setArguments([$connection, new Reference(OutboxTable::class)])
                ->addTag('console.command');
        }
    }

    /**
     * @param Config $config
     */
    private static function scheduler(ContainerBuilder $container, array $config): void
    {
        if (!class_exists(RecurringMessage::class)) {
            throw new LogicException('The scheduled trigger of the outbox needs symfony/scheduler: run "composer require symfony/scheduler".');
        }

        if (!interface_exists(MessageBusInterface::class)) {
            throw new LogicException('The scheduled trigger of the outbox needs symfony/messenger: run "composer require symfony/messenger".');
        }

        $container->register(OutboxSchedule::class, OutboxSchedule::class)
            ->setArguments([$config['scheduler']['frequency']])
            ->addTag('scheduler.schedule_provider', ['name' => OutboxSchedule::NAME]);
        $container->register(RelayOutboxHandler::class, RelayOutboxHandler::class)
            ->setArguments([new Reference(RelayRunner::class), $config['relay']['batch_size'], $config['scheduler']['max_batches']])
            ->addTag('messenger.message_handler', ['handles' => RelayOutbox::class]);
    }
}
