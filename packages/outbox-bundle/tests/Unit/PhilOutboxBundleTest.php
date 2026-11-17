<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Tests\Unit;

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
use Phil\OutboxBundle\Infrastructure\DependencyInjection\OutboxServices;
use Phil\OutboxBundle\Infrastructure\Doctrine\DbalOutboxStore;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxSchemaListener;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;
use Phil\OutboxBundle\Infrastructure\Messenger\MessengerDispatcher;
use Phil\OutboxBundle\Infrastructure\Scheduler\OutboxSchedule;
use Phil\OutboxBundle\Infrastructure\Scheduler\RelayOutbox;
use Phil\OutboxBundle\Infrastructure\Scheduler\RelayOutboxHandler;
use Phil\OutboxBundle\Infrastructure\Worker\RelayRunner;
use Phil\OutboxBundle\PhilOutboxBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilOutboxBundle::class)]
#[CoversClass(OutboxServices::class)]
final class PhilOutboxBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_outbox', $this->extension()->getAlias());
    }

    public function testTheRecordsAreInATableOfTheDefaultConnection(): void
    {
        $container = $this->load([]);
        $connection = new Reference('doctrine.dbal.default_connection');
        $table = new Reference(OutboxTable::class);

        self::assertSame(['phil_outbox'], $container->getDefinition(OutboxTable::class)->getArguments());
        self::assertEquals([$connection, $table], $container->getDefinition(DbalOutboxStore::class)->getArguments());
        self::assertSame(DbalOutboxStore::class, (string) $container->getAlias(OutboxStore::class));
        self::assertEquals([$connection, $table], $container->getDefinition(OutboxSchemaListener::class)->getArguments());
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $container->getDefinition(OutboxSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals([$connection, $table], $container->getDefinition(SchemaCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(SchemaCommand::class)->hasTag('console.command'));
        self::assertFalse($container->hasDefinition(InMemoryOutboxStore::class));
    }

    public function testTheOutboxTheRelayAndTheHousekeepingAreWiredByDefault(): void
    {
        $container = $this->load([]);
        $store = new Reference(OutboxStore::class);
        $clock = new Reference('clock');

        self::assertEquals([new TaggedIteratorArgument('phil_outbox.codec')], $container->getDefinition(Codecs::class)->getArguments());
        self::assertEquals([$store, new Reference(Codecs::class), $clock], $container->getDefinition(StoreOutbox::class)->getArguments());
        self::assertSame(StoreOutbox::class, (string) $container->getAlias(Outbox::class));
        self::assertTrue($container->getAlias(Outbox::class)->isPublic());
        self::assertSame([60, 86_400, 10], $container->getDefinition(Backoff::class)->getArguments());
        self::assertSame('event_dispatcher', (string) $container->getAlias(OutboxServices::DISPATCHER));
        self::assertEquals([$store, new Reference(Codecs::class), new Reference(OutboxServices::DISPATCHER), $clock, new Reference(Backoff::class), 60], $container->getDefinition(Relay::class)->getArguments());
        self::assertTrue($container->getDefinition(Relay::class)->isPublic());
        self::assertEquals([new Reference(Relay::class), $clock, new Reference('lock.factory', ContainerInterface::NULL_ON_INVALID_REFERENCE), 60], $container->getDefinition(RelayRunner::class)->getArguments());
        self::assertEquals([$store, $clock], $container->getDefinition(Housekeeping::class)->getArguments());
        self::assertTrue($container->getDefinition(Housekeeping::class)->isPublic());
        self::assertEquals([new Reference(RelayRunner::class), 100], $container->getDefinition(RelayCommand::class)->getArguments());
        self::assertSame([['command' => 'phil:outbox:relay']], $container->getDefinition(RelayCommand::class)->getTag('console.command'));

        foreach ([DeadShowCommand::class, DeadRetryCommand::class, DeadRemoveCommand::class] as $command) {
            self::assertEquals([new Reference(Housekeeping::class)], $container->getDefinition($command)->getArguments());
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }

        self::assertEquals([new Reference(Housekeeping::class), 604_800], $container->getDefinition(PurgeCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(PurgeCommand::class)->hasTag('console.command'));
        self::assertFalse($container->hasDefinition(RecordedEventCodec::class));
        self::assertFalse($container->hasDefinition(OutboxSchedule::class));
        self::assertFalse($container->hasDefinition(RelayOutboxHandler::class));
        self::assertArrayHasKey(EventCodec::class, $container->getAutoconfiguredInstanceof());
        self::assertTrue($container->getAutoconfiguredInstanceof()[EventCodec::class]->hasTag('phil_outbox.codec'));
    }

    public function testEveryOptionIsWired(): void
    {
        $container = $this->load([[
            'connection' => 'events',
            'table' => 'events_out',
            'clock' => 'app.clock',
            'dispatcher' => 'app.dispatcher',
            'generic_types' => ['quiz.completed', 'quiz.perfect'],
            'relay' => ['batch_size' => 25, 'lease' => 90, 'max_attempts' => 4, 'first_delay' => 5, 'longest_delay' => 600, 'lock' => false],
            'retention' => 3600,
        ]]);
        $connection = new Reference('doctrine.dbal.events_connection');

        self::assertSame(['events_out'], $container->getDefinition(OutboxTable::class)->getArguments());
        self::assertEquals($connection, $container->getDefinition(DbalOutboxStore::class)->getArgument(0));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'events']], $container->getDefinition(OutboxSchemaListener::class)->getTag('doctrine.event_listener'));
        self::assertEquals(new Reference('app.clock'), $container->getDefinition(StoreOutbox::class)->getArgument(2));
        self::assertSame('app.dispatcher', (string) $container->getAlias(OutboxServices::DISPATCHER));
        self::assertSame([['quiz.completed', 'quiz.perfect']], $container->getDefinition(RecordedEventCodec::class)->getArguments());
        self::assertTrue($container->getDefinition(RecordedEventCodec::class)->hasTag('phil_outbox.codec'));
        self::assertSame([5, 600, 4], $container->getDefinition(Backoff::class)->getArguments());
        self::assertSame(90, $container->getDefinition(Relay::class)->getArgument(5));
        self::assertNull($container->getDefinition(RelayRunner::class)->getArgument(2));
        self::assertSame(90, $container->getDefinition(RelayRunner::class)->getArgument(3));
        self::assertSame(25, $container->getDefinition(RelayCommand::class)->getArgument(1));
        self::assertSame(3600, $container->getDefinition(PurgeCommand::class)->getArgument(1));
    }

    public function testTheRecordsCanBeKeptInMemory(): void
    {
        $container = $this->load([['store' => 'memory']]);

        self::assertSame(InMemoryOutboxStore::class, (string) $container->getAlias(OutboxStore::class));
        self::assertSame([['method' => 'reset']], $container->getDefinition(InMemoryOutboxStore::class)->getTag('kernel.reset'));
        self::assertFalse($container->hasDefinition(DbalOutboxStore::class));
        self::assertFalse($container->hasDefinition(OutboxSchemaListener::class));
        self::assertFalse($container->hasDefinition(SchemaCommand::class));
        self::assertTrue($container->hasDefinition(RelayCommand::class));
    }

    public function testTheScheduledTriggerIsWiredWhenEnabled(): void
    {
        $container = $this->load([['scheduler' => ['enabled' => true, 'frequency' => 30, 'max_batches' => 3], 'relay' => ['batch_size' => 40]]]);

        self::assertSame([30], $container->getDefinition(OutboxSchedule::class)->getArguments());
        self::assertSame([['name' => 'phil_outbox']], $container->getDefinition(OutboxSchedule::class)->getTag('scheduler.schedule_provider'));
        self::assertEquals([new Reference(RelayRunner::class), 40, 3], $container->getDefinition(RelayOutboxHandler::class)->getArguments());
        self::assertSame([['handles' => RelayOutbox::class]], $container->getDefinition(RelayOutboxHandler::class)->getTag('messenger.message_handler'));
    }

    public function testTheScheduleTicksEveryMinuteForTenBatchesByDefault(): void
    {
        $container = $this->load([['scheduler' => true]]);

        self::assertSame([60], $container->getDefinition(OutboxSchedule::class)->getArguments());
        self::assertEquals([new Reference(RelayRunner::class), Relay::BATCH, 10], $container->getDefinition(RelayOutboxHandler::class)->getArguments());
    }

    public function testTheStoreIsNamedByItsKind(): void
    {
        self::assertSame(DbalOutboxStore::class, (string) $this->load([['store' => 'dbal']])->getAlias(OutboxStore::class));

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('phil_outbox.store');

        $this->load([['store' => 'redis']]);
    }

    public function testTheMessengerBridgeReplacesTheDispatcherWhenEnabled(): void
    {
        $container = $this->load([['messenger' => ['enabled' => true, 'bus' => 'app.event_bus']]]);

        self::assertSame(MessengerDispatcher::class, $container->getDefinition(OutboxServices::DISPATCHER)->getClass());
        self::assertEquals([new Reference('app.event_bus')], $container->getDefinition(OutboxServices::DISPATCHER)->getArguments());
        self::assertEquals([new Reference('messenger.default_bus')], $this->load([['messenger' => true]])->getDefinition(OutboxServices::DISPATCHER)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedConfigurations(): iterable
    {
        yield 'an unknown store' => [['store' => 'redis'], 'The value "redis" is not allowed for path "phil_outbox.store"'];
        yield 'a connection of another shape' => [['connection' => 'a-b'], 'The connection is named by 1 to 64 letters, digits or "_"'];
        yield 'a connection with a newline' => [['connection' => "default\n"], 'The connection is named by 1 to 64 letters, digits or "_"'];
        yield 'a connection too long' => [['connection' => str_repeat('a', 65)], 'The connection is named by 1 to 64 letters, digits or "_"'];
        yield 'a table of another shape' => [['table' => 'Outbox'], 'The table is named by 1 to 48 lowercase letters'];
        yield 'a generic type of another shape' => [['generic_types' => ['Quiz']], 'An event type is 1 to 120 lowercase letters and digits'];
        yield 'a batch too large' => [['relay' => ['batch_size' => 1001]], 'phil_outbox.relay.batch_size'];
        yield 'no batch' => [['relay' => ['batch_size' => 0]], 'phil_outbox.relay.batch_size'];
        yield 'a lease too long' => [['relay' => ['lease' => 3601]], 'phil_outbox.relay.lease'];
        yield 'no lease' => [['relay' => ['lease' => 0]], 'phil_outbox.relay.lease'];
        yield 'too many attempts' => [['relay' => ['max_attempts' => 101]], 'phil_outbox.relay.max_attempts'];
        yield 'no attempt' => [['relay' => ['max_attempts' => 0]], 'phil_outbox.relay.max_attempts'];
        yield 'no first delay' => [['relay' => ['first_delay' => 0]], 'phil_outbox.relay.first_delay'];
        yield 'no longest delay' => [['relay' => ['longest_delay' => 0]], 'phil_outbox.relay.longest_delay'];
        yield 'a longest delay under the first' => [['relay' => ['first_delay' => 10, 'longest_delay' => 9]], 'The longest delay is not under the first.'];
        yield 'a negative retention' => [['retention' => -1], 'phil_outbox.retention'];
        yield 'no frequency' => [['scheduler' => ['frequency' => 0]], 'phil_outbox.scheduler.frequency'];
        yield 'a frequency too long' => [['scheduler' => ['frequency' => 86_401]], 'phil_outbox.scheduler.frequency'];
        yield 'no batch of a tick' => [['scheduler' => ['max_batches' => 0]], 'phil_outbox.scheduler.max_batches'];
        yield 'too many batches of a tick' => [['scheduler' => ['max_batches' => 1001]], 'phil_outbox.scheduler.max_batches'];
        yield 'an empty bus' => [['messenger' => ['bus' => '']], 'phil_outbox.messenger.bus'];
        yield 'an empty clock' => [['clock' => ''], 'phil_outbox.clock'];
        yield 'an empty dispatcher' => [['dispatcher' => ''], 'phil_outbox.dispatcher'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedConfigurations')]
    public function testAConfigurationOutOfBoundsIsRefused(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->load([$config]);
    }

    public function testTheBoundsOfTheConfigurationAreAccepted(): void
    {
        $container = $this->load([[
            'connection' => str_repeat('a', 64),
            'table' => '_'.str_repeat('a', 47),
            'relay' => ['batch_size' => 1000, 'lease' => 3600, 'max_attempts' => 100, 'first_delay' => 7, 'longest_delay' => 7],
            'retention' => 0,
            'scheduler' => ['enabled' => true, 'frequency' => 86_400, 'max_batches' => 1000],
        ]]);

        self::assertSame(1000, $container->getDefinition(RelayCommand::class)->getArgument(1));
        self::assertSame([1, 1, 1], $this->load([['relay' => ['batch_size' => 1, 'lease' => 1, 'max_attempts' => 1, 'first_delay' => 1, 'longest_delay' => 1], 'scheduler' => ['enabled' => true, 'frequency' => 1, 'max_batches' => 1]]])->getDefinition(Backoff::class)->getArguments());
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
        $extension = new PhilOutboxBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
