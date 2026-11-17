<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle;

use Doctrine\DBAL\Connection;
use Override;
use Phil\TenancyBundle\Application\Contract\TenantPlacement;
use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Infrastructure\Console\TenantOption;
use Phil\TenancyBundle\Infrastructure\Http\ResolveActiveTenant;
use Phil\TenancyBundle\Infrastructure\Http\ResolveHostTenant;
use Phil\TenancyBundle\PhilTenancyBundle;
use Phil\TenancyDatabaseBundle\Application\Contract\TenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\CacheTenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\DbalTenantDatabaseAvailability;
use Phil\TenancyDatabaseBundle\Infrastructure\Availability\TenantAvailabilityGuard;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\CreateTenantDatabaseCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrateTenantDatabasesCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrationTargets;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MoveTenantCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Database\TenantDatabaseCreator;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\PinnedConnection;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\RollBackOpenTransactions;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\StoreSchemaListener;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantConnectionSwitch;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseMiddleware;
use Phil\TenancyDatabaseBundle\Infrastructure\Doctrine\TenantDatabaseRouter;
use Phil\TenancyDatabaseBundle\Infrastructure\Http\RefuseUnavailableTenant;
use Phil\TenancyDatabaseBundle\Infrastructure\Messenger\RefuseUnavailableTenantMiddleware;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use Phil\TenancyDatabaseBundle\Infrastructure\Move\TenantMover;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\ConfiguredPlacements;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\DbalPlacementStore;
use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Process\Process;

use function Symfony\Component\DependencyInjection\Loader\Configurator\iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * A database for each tenant, or the shared one, wired on one Doctrine connection: the DBAL middleware that opens it
 * on the database of the active tenant, the activation that moves it as tenants are entered and left, the placements
 * of `dedicated`, of a table of the shared database or of the application's own `TenantPlacement`, the suspension of
 * the databases whose migration failed and of the tenants being moved, kept in a table of the shared database,
 * answered with a `503` and retried by Messenger, and the commands that migrate every database, create a tenant's and
 * move a tenant between them.
 *
 * ```yaml
 * phil_tenancy_database:
 *     connection: default
 *     dedicated:
 *         wholesale: tenant_wholesale
 * ```
 */
final class PhilTenancyDatabaseBundle extends AbstractBundle
{
    /**
     * The cache pool of the `cache` availability, built on `cache.app` and shared by every tenant; not durable.
     */
    public const string AVAILABILITY_POOL = 'phil_tenancy_database.availability';

    /**
     * The connection of the `dbal` stores, pinned to the shared database, when no `store_connection` is named.
     */
    public const string STORE_CONNECTION = 'phil_tenancy_database.store_connection';

    /**
     * The value of `placement` and `availability` that keeps them in the tables of the shared database.
     */
    public const string DBAL = 'dbal';

    /**
     * The value of `availability` that keeps the suspensions in the {@see self::AVAILABILITY_POOL} pool.
     */
    public const string CACHE = 'cache';

    /**
     * The priority of the rollback of the transactions left open at `kernel.terminate` and in a Messenger worker: after
     * the listeners of the application, which may still write.
     */
    public const int ROLLBACK_PRIORITY = -2048;

    #[Override]
    protected string $extensionAlias = 'phil_tenancy_database';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')
                    ->info('The Doctrine connection that follows the active tenant to its database.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('entity_manager')
                    ->info('The entity manager whose tenant-scoped tables a move copies.')
                    ->defaultValue('default')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('placement')
                    ->info('Where the placements are read: null for the tenants of "dedicated", "dbal" for the table of the shared database, which a move updates, or the id of a TenantPlacement service, which may also implement PlacedTenants and PlacementStore.')
                    ->defaultNull()
                ->end()
                ->arrayNode('dedicated')
                    ->info('The tenants with a database of their own, and its name; every other tenant is in the shared database.')
                    ->useAttributeAsKey('tenant')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->cannotBeEmpty()->end()
                ->end()
                ->scalarNode('credentials')
                    ->info('The id of a TenantCredentials service, for dedicated databases reached with another account or on another server.')
                    ->defaultNull()
                ->end()
                ->scalarNode('availability')
                    ->info('Where the suspensions are kept: "dbal" for the table of the shared database, "cache" for the phil_tenancy_database.availability pool, which an eviction or a cleared cache empties, or the id of a TenantDatabaseAvailability service.')
                    ->defaultValue(self::DBAL)
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('store_connection')
                    ->info('The Doctrine connection on the shared database that the "dbal" stores use, and that does not follow the tenant; by default, a connection of their own pinned to the shared database of "connection".')
                    ->defaultNull()
                ->end()
                ->integerNode('retry_after')
                    ->info('The seconds of the Retry-After of a 503 for a suspended tenant.')
                    ->defaultValue(120)
                    ->min(1)
                ->end()
                ->arrayNode('migrations')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('console')
                            ->info('The console that runs the migration command of each database.')
                            ->defaultValue('%kernel.project_dir%/bin/console')
                            ->cannotBeEmpty()
                        ->end()
                        ->arrayNode('command')
                            ->info('The command, and its arguments, that migrates the database the connection is in.')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->defaultValue(['doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'])
                            ->requiresAtLeastOneElement()
                        ->end()
                        ->integerNode('concurrency')
                            ->info('The migrations that run at a time.')
                            ->defaultValue(4)
                            ->min(1)
                            ->max(MigrationRunner::MAX_CONCURRENCY)
                        ->end()
                        ->integerNode('timeout')
                            ->info('The seconds each migration may run; 0 for no limit.')
                            ->defaultValue(3600)
                            ->min(0)
                        ->end()
                        ->integerNode('grace_period')
                            ->info('The seconds a running migration is given to stop after SIGTERM when the command is stopped, before SIGKILL.')
                            ->defaultValue(10)
                            ->min(0)
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => self::DBAL === $config['placement'] && [] !== $config['dedicated'])
                ->thenInvalid('The placements are read from the table: "dedicated" would be ignored. Record those tenants with PlacementStore::place() instead.')
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => $config['store_connection'] === $config['connection'])
                ->thenInvalid('The store connection follows the active tenant to its database: name a connection on the shared database that does not, or leave store_connection out.')
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('framework')) {
            return;
        }

        // The bundle's own tree, read before its extension loads: the pool must reach FrameworkBundle first.
        /** @var array{availability: string} $config */
        $config = new Processor()->processConfiguration(new Configuration($this, $builder, $this->extensionAlias), $builder->getExtensionConfig($this->extensionAlias));

        if (self::CACHE !== $config['availability']) {
            return;
        }

        // The suspensions are read by every process and every tenant: a pool of its own, which no tenant scopes.
        $container->extension('framework', ['cache' => ['pools' => [self::AVAILABILITY_POOL => ['adapter' => 'cache.app']]]]);

        if ($builder->hasExtension('phil_tenancy')) {
            $container->extension('phil_tenancy', ['cache' => ['global_pools' => [self::AVAILABILITY_POOL]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{connection: string, entity_manager: string, placement: ?string, dedicated: array<string, string>, credentials: ?string, availability: string, store_connection: ?string, retry_after: int, migrations: array{console: string, command: list<string>, concurrency: int, timeout: int, grace_period: int}} $config */
        $services = $container->services();
        $connection = service(\sprintf('doctrine.dbal.%s_connection', $config['connection']));

        $this->loadStores($config['connection'], $config['placement'], $config['dedicated'], $config['availability'], $config['store_connection'], $container);

        $services->set(TenantDatabaseRouter::class)
            ->args([null === $config['credentials'] ? null : service($config['credentials'])])
            ->tag('kernel.reset', ['method' => 'reset']);
        $services->set(TenantDatabaseMiddleware::class)
            ->args([service(TenantDatabaseRouter::class)])
            ->tag('doctrine.middleware', ['connection' => $config['connection']]);
        $services->set(TenantConnectionSwitch::class)
            ->args([$connection, service(TenantDatabaseRouter::class), service(TenantPlacement::class), service('doctrine')->nullOnInvalid(), service('logger')->nullOnInvalid()])
            ->tag(PhilTenancyBundle::ACTIVATION_TAG)
            ->tag('kernel.reset', ['method' => 'reset']);
        $this->loadRollback($config['connection'], null === $config['store_connection'] && (self::DBAL === $config['placement'] || self::DBAL === $config['availability']), $container);

        $services->set(TenantAvailabilityGuard::class)->args([service(TenantPlacement::class), service(TenantDatabaseAvailability::class)]);
        // Once the host's tenant is entered, before the firewall reads an account from its database; and once the
        // account's tenant is resolved, after the firewall.
        $services->set(RefuseUnavailableTenant::class)
            ->args([service(TenantContext::class), service(TenantAvailabilityGuard::class), $config['retry_after']])
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onRequest', 'after' => [ResolveHostTenant::class], 'before' => ['security.firewall', 'debug.security.firewall']])
            ->tag('kernel.event_listener', ['event' => KernelEvents::REQUEST, 'method' => 'onRequest', 'after' => [ResolveActiveTenant::class]]);

        if (interface_exists(MiddlewareInterface::class)) {
            $services->set(RefuseUnavailableTenantMiddleware::class)->args([service(TenantContext::class), service(TenantAvailabilityGuard::class)]);
        }

        $services->set(TenantDatabaseCreator::class)->args([$connection, service(TenantDatabaseRouter::class)]);
        $services->set(TenantMover::class)
            ->args([
                $connection,
                service(TenantDatabaseRouter::class),
                service(\sprintf('doctrine.orm.%s_entity_manager', $config['entity_manager'])),
                service(TenantPlacement::class),
                service(TenantDatabaseAvailability::class),
                service(TenantContext::class),
                service('logger')->nullOnInvalid(),
            ]);

        if (!class_exists(ConsoleEvents::class) || !class_exists(Process::class)) {
            return;
        }

        $migrations = $config['migrations'];
        $services->set(MigrationRunner::class)->args([
            [$migrations['console'], ...$migrations['command']],
            service(TenantDatabaseAvailability::class),
            $migrations['concurrency'],
            0 === $migrations['timeout'] ? null : (float) $migrations['timeout'],
            null,
            (float) $migrations['grace_period'],
        ]);
        $services->set(MigrationTargets::class)->args([service(TenantPlacement::class)]);
        $services->set(MigrateTenantDatabasesCommand::class)
            ->args([service(MigrationTargets::class), service(MigrationRunner::class)])
            ->tag('console.command');
        $services->set(CreateTenantDatabaseCommand::class)
            ->args([service(TenantPlacement::class), service(TenantDatabaseCreator::class), service(MigrationRunner::class)])
            ->tag('console.command');
        $services->set(MoveTenantCommand::class)
            ->args([service(TenantMover::class)])
            ->tag('console.command');
    }

    /**
     * The rollback of the transactions left open at the end of each request, command and message, on the connection
     * that follows the tenant and on the store connection of the bundle; a connection not yet created is skipped.
     *
     * @param bool $store whether the bundle declares its own store connection
     */
    private function loadRollback(string $connection, bool $store, ContainerConfigurator $container): void
    {
        $connections = [service(\sprintf('doctrine.dbal.%s_connection', $connection))->ignoreOnUninitialized()];

        if ($store) {
            $connections[] = service(self::STORE_CONNECTION)->ignoreOnUninitialized();
        }

        $listener = $container->services()->set(RollBackOpenTransactions::class)
            ->args([iterator($connections), service(TenantContext::class), service('logger')->nullOnInvalid()])
            ->tag('kernel.event_listener', ['event' => KernelEvents::TERMINATE, 'method' => 'endUnitOfWork', 'priority' => self::ROLLBACK_PRIORITY]);

        if (class_exists(ConsoleEvents::class)) {
            // Before the tenant of the command is left, which would close the connection of a dedicated database.
            $listener->tag('kernel.event_listener', ['event' => ConsoleEvents::TERMINATE, 'method' => 'endUnitOfWork', 'before' => [TenantOption::class]]);
        }

        if (class_exists(WorkerMessageHandledEvent::class)) {
            $listener
                ->tag('kernel.event_listener', ['event' => WorkerMessageHandledEvent::class, 'method' => 'endUnitOfWork', 'priority' => self::ROLLBACK_PRIORITY])
                ->tag('kernel.event_listener', ['event' => WorkerMessageFailedEvent::class, 'method' => 'endUnitOfWork', 'priority' => self::ROLLBACK_PRIORITY]);
        }
    }

    /**
     * The placements and the suspensions: from the configuration, the tables of the shared database, the cache pool
     * or the application's own services.
     *
     * @param array<string, string> $dedicated
     */
    private function loadStores(string $connection, ?string $placement, array $dedicated, string $availability, ?string $named, ContainerConfigurator $container): void
    {
        $services = $container->services();
        $placements = self::DBAL === $placement;
        $suspensions = self::DBAL === $availability;

        if (null === $placement) {
            // Checked as the container compiles, by the constructor that will read it.
            new ConfiguredPlacements($dedicated);
            $services->set(ConfiguredPlacements::class)->args([$dedicated]);
            $placement = ConfiguredPlacements::class;
        }

        if (self::CACHE === $availability) {
            $services->set(CacheTenantDatabaseAvailability::class)->args([service(self::AVAILABILITY_POOL)]);
            $availability = CacheTenantDatabaseAvailability::class;
        }

        if ($placements || $suspensions) {
            $store = service(self::STORE_CONNECTION);

            if (null === $named) {
                // Closed at the end of each unit of work, so that a worker never reads through a connection the server dropped.
                $services->set(self::STORE_CONNECTION, Connection::class)
                    ->factory(PinnedConnection::shared(...))
                    ->args([service(\sprintf('doctrine.dbal.%s_connection', $connection)), service(TenantDatabaseRouter::class)])
                    ->tag('kernel.reset', ['method' => 'close']);
            } else {
                $store = service(\sprintf('doctrine.dbal.%s_connection', $named));
            }

            $schema = $named ?? $connection;
            $services->set(StoreSchemaListener::class)
                ->args([service(\sprintf('doctrine.dbal.%s_connection', $schema)), $placements, $suspensions])
                ->tag('doctrine.event_listener', ['event' => 'postGenerateSchema', 'connection' => $schema]);

            if ($placements) {
                $services->set(DbalPlacementStore::class)->args([$store]);
                $placement = DbalPlacementStore::class;
            }

            if ($suspensions) {
                $services->set(DbalTenantDatabaseAvailability::class)->args([$store]);
                $availability = DbalTenantDatabaseAvailability::class;
            }
        }

        $services->alias(TenantPlacement::class, $placement);
        $services->alias(TenantDatabaseAvailability::class, $availability);
    }
}
