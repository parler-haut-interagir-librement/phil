<?php

declare(strict_types=1);

namespace Phil\OutboxBundle;

use Override;
use Phil\Outbox\Backoff;
use Phil\Outbox\Housekeeping;
use Phil\Outbox\OutboxRecord;
use Phil\Outbox\Relay;
use Phil\OutboxBundle\Infrastructure\DependencyInjection\OutboxServices;
use Phil\OutboxBundle\Infrastructure\Doctrine\OutboxTable;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * phil/outbox in Symfony: the records in a table of the application's DBAL connection, so that a record joins the
 * transaction of the write it describes; the codecs registered by tag; a relay command safe in worker mode, with an
 * optional scheduled trigger; commands for the dead records and the retention; and an optional bridge to a Messenger
 * bus.
 *
 * ```yaml
 * phil_outbox:
 *     connection: default
 *     generic_types: [quiz.completed]
 *     relay:
 *         batch_size: 100
 *         lease: 60
 *         unknown_type_grace: 3600
 * ```
 *
 * @phpstan-import-type Config from OutboxServices
 */
final class PhilOutboxBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_outbox';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('store')
                    ->info('Where the records are kept: "dbal", a table of a DBAL connection; "memory", one process, for tests.')
                    ->values([OutboxServices::STORE_DBAL, OutboxServices::STORE_MEMORY])
                    ->defaultValue(OutboxServices::STORE_DBAL)
                ->end()
                ->scalarNode('connection')
                    ->info("The name of the DBAL connection of DoctrineBundle the table is in: the one of the packages' own writes, so that a record joins their transaction.")
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $name): bool => !\is_string($name) || 1 !== preg_match('/^\w{1,64}$/D', $name))
                        ->thenInvalid('The connection is named by 1 to 64 letters, digits or "_", not %s.')
                    ->end()
                ->end()
                ->scalarNode('table')
                    ->info('The name of the table: 1 to 48 lowercase letters, digits or "_", not starting with a digit.')
                    ->defaultValue(OutboxTable::DEFAULT_NAME)
                    ->cannotBeEmpty()
                    ->validate()
                        ->ifTrue(static fn (mixed $name): bool => !\is_string($name) || 1 !== preg_match(OutboxTable::NAME_PATTERN, $name))
                        ->thenInvalid('The table is named by 1 to 48 lowercase letters, digits or "_", not starting with a digit, not %s.')
                    ->end()
                ->end()
                ->scalarNode('clock')
                    ->info('The service id of the clock of Symfony the records are dated by.')
                    ->defaultValue('clock')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('dispatcher')
                    ->info('The service id of the PSR-14 dispatcher the relay tells the listeners through.')
                    ->defaultValue('event_dispatcher')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('generic_types')
                    ->info('The event types rebuilt as Phil\Outbox\RecordedEvent, without a codec of their own.')
                    ->scalarPrototype()
                        ->validate()
                            ->ifTrue(static fn (mixed $type): bool => !\is_string($type) || 1 !== preg_match(OutboxRecord::TYPE_PATTERN, $type))
                            ->thenInvalid('An event type is 1 to 120 lowercase letters and digits, in words joined by ".", "_" or "-", not %s.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('relay')
                    ->info('The relay.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('batch_size')->info('The most records of a batch.')->min(1)->max(Relay::MAX_BATCH)->defaultValue(Relay::BATCH)->end()
                        ->integerNode('lease')->info('The seconds a batch holds its records: longer than the slowest listener.')->min(1)->max(3600)->defaultValue(Relay::LEASE)->end()
                        ->integerNode('unknown_type_grace')->info('The seconds after it happened that a record of a type no codec rebuilds is tried again before it is dead: covers a worker deployed before the code that records the type.')->min(0)->max(Relay::MAX_UNKNOWN_TYPE_GRACE)->defaultValue(Relay::UNKNOWN_TYPE_GRACE)->end()
                        ->integerNode('max_attempts')->info('The failed attempts after which a record is dead.')->min(1)->max(100)->defaultValue(Backoff::MAX_ATTEMPTS)->end()
                        ->integerNode('first_delay')->info('The seconds before a record that failed once is tried again, doubled after each failure.')->min(1)->defaultValue(Backoff::FIRST_DELAY)->end()
                        ->integerNode('longest_delay')->info('The longest delay before a record is tried again, in seconds.')->min(1)->defaultValue(Backoff::LONGEST_DELAY)->end()
                        ->booleanNode('lock')->info('Whether one run of the relay at a time holds the lock "phil_outbox.relay" of the lock factory, when there is one.')->defaultTrue()->end()
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $relay): bool => $relay['longest_delay'] < $relay['first_delay'])
                        ->thenInvalid('The longest delay is not under the first.')
                    ->end()
                ->end()
                ->integerNode('retention')
                    ->info('The seconds a delivered record is kept before phil:outbox:purge removes it.')
                    ->min(0)
                    ->defaultValue(Housekeeping::RETENTION)
                ->end()
                ->arrayNode('scheduler')
                    ->info('A schedule "phil_outbox" of Symfony Scheduler that relays the outbox, consumed by a Messenger worker.')
                    ->canBeEnabled()
                    ->children()
                        ->integerNode('frequency')->info('The seconds between two ticks.')->min(1)->max(86_400)->defaultValue(60)->end()
                        ->integerNode('max_batches')->info('The most batches of a tick.')->min(1)->max(1000)->defaultValue(10)->end()
                    ->end()
                ->end()
                ->arrayNode('messenger')
                    ->info('Relays the events to a Messenger bus instead of the PSR-14 dispatcher.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('bus')->info('The service id of the bus.')->defaultValue('messenger.default_bus')->cannotBeEmpty()->end()
                    ->end()
                ->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config the configuration, processed by the tree above
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        OutboxServices::register($builder, $config);
    }
}
