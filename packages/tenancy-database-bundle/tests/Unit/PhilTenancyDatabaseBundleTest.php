<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
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
use Phil\TenancyDatabaseBundle\PhilTenancyDatabaseBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;

#[CoversClass(PhilTenancyDatabaseBundle::class)]
final class PhilTenancyDatabaseBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_tenancy_database', $this->extension()->getAlias());
    }

    public function testTheDefaultWiring(): void
    {
        $container = $this->load([]);

        self::assertSame([[]], array_values($container->getDefinition(ConfiguredPlacements::class)->getArguments()));
        self::assertSame(ConfiguredPlacements::class, $this->id($container->getAlias(TenantPlacement::class)));
        self::assertSame(DbalTenantDatabaseAvailability::class, $this->id($container->getAlias(TenantDatabaseAvailability::class)));
        self::assertEquals([new Reference(PhilTenancyDatabaseBundle::STORE_CONNECTION)], $container->getDefinition(DbalTenantDatabaseAvailability::class)->getArguments());
        self::assertFalse($container->hasDefinition(CacheTenantDatabaseAvailability::class));
        self::assertFalse($container->hasDefinition(DbalPlacementStore::class));

        $store = $container->getDefinition(PhilTenancyDatabaseBundle::STORE_CONNECTION);
        self::assertSame(Connection::class, $store->getClass());
        self::assertSame([PinnedConnection::class, 'shared'], $store->getFactory());
        self::assertSame(['doctrine.dbal.default_connection', TenantDatabaseRouter::class], $this->arguments($container, PhilTenancyDatabaseBundle::STORE_CONNECTION));
        self::assertSame([['method' => 'close']], $store->getTag('kernel.reset'));

        $schema = $container->getDefinition(StoreSchemaListener::class);
        self::assertSame(['doctrine.dbal.default_connection', false, true], $this->arguments($container, StoreSchemaListener::class));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'default']], $schema->getTag('doctrine.event_listener'));

        $router = $container->getDefinition(TenantDatabaseRouter::class);
        self::assertSame([null], $router->getArguments());
        self::assertSame([['method' => 'reset']], $router->getTag('kernel.reset'));

        self::assertSame([['connection' => 'default']], $container->getDefinition(TenantDatabaseMiddleware::class)->getTag('doctrine.middleware'));

        $switch = $container->getDefinition(TenantConnectionSwitch::class);
        self::assertSame('doctrine.dbal.default_connection', $this->id($switch->getArgument(0)));
        self::assertSame([[]], $switch->getTag(PhilTenancyBundle::ACTIVATION_TAG));
        self::assertSame([['method' => 'reset']], $switch->getTag('kernel.reset'));

        self::assertTrue($container->hasDefinition(TenantAvailabilityGuard::class));
        $listener = $container->getDefinition(RefuseUnavailableTenant::class);
        self::assertSame(120, $listener->getArgument(2));
        self::assertSame([
            ['event' => 'kernel.request', 'method' => 'onRequest', 'after' => [ResolveHostTenant::class], 'before' => ['security.firewall', 'debug.security.firewall']],
            ['event' => 'kernel.request', 'method' => 'onRequest', 'after' => [ResolveActiveTenant::class]],
        ], $listener->getTag('kernel.event_listener'));

        self::assertTrue($container->hasDefinition(RefuseUnavailableTenantMiddleware::class));
        self::assertSame('doctrine.dbal.default_connection', $this->id($container->getDefinition(TenantDatabaseCreator::class)->getArgument(0)));
        self::assertSame('doctrine.orm.default_entity_manager', $this->id($container->getDefinition(TenantMover::class)->getArgument(2)));

        $runner = $container->getDefinition(MigrationRunner::class);
        self::assertSame(['%kernel.project_dir%/bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'], $runner->getArgument(0));
        self::assertSame(4, $runner->getArgument(2));
        self::assertSame(3600.0, $runner->getArgument(3));
        self::assertNull($runner->getArgument(4));
        self::assertSame(10.0, $runner->getArgument(5));
        self::assertTrue($container->hasDefinition(MigrationTargets::class));

        foreach ([MigrateTenantDatabasesCommand::class, CreateTenantDatabaseCommand::class, MoveTenantCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }
    }

    public function testEachServiceReceivesTheServicesItNeeds(): void
    {
        $container = $this->load([]);
        $placement = TenantPlacement::class;
        $availability = TenantDatabaseAvailability::class;

        self::assertSame([TenantDatabaseRouter::class], $this->arguments($container, TenantDatabaseMiddleware::class));
        self::assertSame([$placement, $availability], $this->arguments($container, TenantAvailabilityGuard::class));
        self::assertSame([TenantContext::class, TenantAvailabilityGuard::class], $this->arguments($container, RefuseUnavailableTenantMiddleware::class));
        self::assertSame([$placement], $this->arguments($container, MigrationTargets::class));
        self::assertSame([MigrationTargets::class, MigrationRunner::class], $this->arguments($container, MigrateTenantDatabasesCommand::class));
        self::assertSame([$placement, TenantDatabaseCreator::class, MigrationRunner::class], $this->arguments($container, CreateTenantDatabaseCommand::class));
        self::assertSame([TenantMover::class], $this->arguments($container, MoveTenantCommand::class));
        self::assertSame(['doctrine.dbal.default_connection', TenantDatabaseRouter::class, $placement, 'doctrine', 'logger'], $this->arguments($container, TenantConnectionSwitch::class));
        self::assertSame([TenantContext::class, TenantAvailabilityGuard::class, 120], $this->arguments($container, RefuseUnavailableTenant::class));
        self::assertSame([['%kernel.project_dir%/bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'], $availability, 4, 3600.0, null, 10.0], $this->arguments($container, MigrationRunner::class));
    }

    public function testTheOpenTransactionsAreRolledBackAtTheEndOfEachUnitOfWork(): void
    {
        $container = $this->load([]);
        $rollback = $container->getDefinition(RollBackOpenTransactions::class);

        self::assertSame([
            ['event' => 'kernel.terminate', 'method' => 'endUnitOfWork', 'priority' => -2048],
            ['event' => 'console.terminate', 'method' => 'endUnitOfWork', 'before' => [TenantOption::class]],
            ['event' => WorkerMessageHandledEvent::class, 'method' => 'endUnitOfWork', 'priority' => -2048],
            ['event' => WorkerMessageFailedEvent::class, 'method' => 'endUnitOfWork', 'priority' => -2048],
        ], $rollback->getTag('kernel.event_listener'));
        self::assertSame(-2048, PhilTenancyDatabaseBundle::ROLLBACK_PRIORITY);
        self::assertSame([['doctrine.dbal.default_connection', PhilTenancyDatabaseBundle::STORE_CONNECTION], TenantContext::class, 'logger'], $this->rollbackArguments($container));
        $logger = $rollback->getArgument(2);
        self::assertInstanceOf(Reference::class, $logger);
        self::assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $logger->getInvalidBehavior());
    }

    public function testTheRollbackSkipsTheStoreConnectionOfTheApplication(): void
    {
        self::assertSame([['doctrine.dbal.tenants_connection'], TenantContext::class, 'logger'], $this->rollbackArguments($this->load([['connection' => 'tenants', 'store_connection' => 'shared']])));
        self::assertSame([['doctrine.dbal.default_connection'], TenantContext::class, 'logger'], $this->rollbackArguments($this->load([['availability' => 'cache']])));
        self::assertSame([['doctrine.dbal.default_connection', PhilTenancyDatabaseBundle::STORE_CONNECTION], TenantContext::class, 'logger'], $this->rollbackArguments($this->load([['placement' => 'dbal', 'availability' => 'cache']])));
    }

    public function testTheNamesOfTheDedicatedTenantsAreKeptAsTheyAreWritten(): void
    {
        $container = $this->load([['dedicated' => ['north-shop' => 'tenant_north']]]);

        self::assertSame([['north-shop' => 'tenant_north']], array_values($container->getDefinition(ConfiguredPlacements::class)->getArguments()));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function limits(): iterable
    {
        yield 'a Retry-After of one second' => [['retry_after' => 1], true];
        yield 'no Retry-After' => [['retry_after' => 0], false];
        yield 'one migration at a time' => [['migrations' => ['concurrency' => 1]], true];
        yield 'no migration at a time' => [['migrations' => ['concurrency' => 0]], false];
        yield 'the most migrations at a time' => [['migrations' => ['concurrency' => MigrationRunner::MAX_CONCURRENCY]], true];
        yield 'too many migrations at a time' => [['migrations' => ['concurrency' => MigrationRunner::MAX_CONCURRENCY + 1]], false];
        yield 'no limit of time' => [['migrations' => ['timeout' => 0]], true];
        yield 'a negative time' => [['migrations' => ['timeout' => -1]], false];
        yield 'no grace period' => [['migrations' => ['grace_period' => 0]], true];
        yield 'a negative grace period' => [['migrations' => ['grace_period' => -1]], false];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('limits')]
    public function testTheLimitsOfTheSettings(array $config, bool $accepted): void
    {
        if (!$accepted) {
            $this->expectException(InvalidConfigurationException::class);
        }

        $container = $this->load([$config]);

        self::assertTrue($container->hasDefinition(MigrationRunner::class));
    }

    public function testTheServicesOfTheApplication(): void
    {
        $container = $this->load([[
            'connection' => 'tenants',
            'entity_manager' => 'tenants',
            'placement' => 'app.placements',
            'credentials' => 'app.credentials',
            'availability' => 'app.availability',
            'retry_after' => 30,
            'migrations' => ['console' => 'bin/console', 'command' => ['app:migrate'], 'concurrency' => 8, 'timeout' => 0, 'grace_period' => 30],
        ]]);

        self::assertFalse($container->hasDefinition(ConfiguredPlacements::class));
        self::assertSame('app.placements', $this->id($container->getAlias(TenantPlacement::class)));
        self::assertFalse($container->hasDefinition(CacheTenantDatabaseAvailability::class));
        self::assertFalse($container->hasDefinition(DbalTenantDatabaseAvailability::class));
        self::assertFalse($container->hasDefinition(PhilTenancyDatabaseBundle::STORE_CONNECTION));
        self::assertFalse($container->hasDefinition(StoreSchemaListener::class));
        self::assertSame('app.availability', $this->id($container->getAlias(TenantDatabaseAvailability::class)));
        self::assertEquals([new Reference('app.credentials')], $container->getDefinition(TenantDatabaseRouter::class)->getArguments());
        self::assertSame([['connection' => 'tenants']], $container->getDefinition(TenantDatabaseMiddleware::class)->getTag('doctrine.middleware'));
        self::assertSame('doctrine.dbal.tenants_connection', $this->id($container->getDefinition(TenantConnectionSwitch::class)->getArgument(0)));
        self::assertSame('doctrine.orm.tenants_entity_manager', $this->id($container->getDefinition(TenantMover::class)->getArgument(2)));
        self::assertSame(30, $container->getDefinition(RefuseUnavailableTenant::class)->getArgument(2));

        $runner = $container->getDefinition(MigrationRunner::class);
        self::assertSame(['bin/console', 'app:migrate'], $runner->getArgument(0));
        self::assertSame(8, $runner->getArgument(2));
        self::assertNull($runner->getArgument(3));
        self::assertSame(30.0, $runner->getArgument(5));
    }

    public function testThePlacementsAndTheSuspensionsInTheTablesOfANamedConnection(): void
    {
        $container = $this->load([['placement' => 'dbal', 'store_connection' => 'shared']]);

        self::assertFalse($container->hasDefinition(ConfiguredPlacements::class));
        self::assertSame(DbalPlacementStore::class, $this->id($container->getAlias(TenantPlacement::class)));
        self::assertSame(['doctrine.dbal.shared_connection'], $this->arguments($container, DbalPlacementStore::class));
        self::assertSame(DbalTenantDatabaseAvailability::class, $this->id($container->getAlias(TenantDatabaseAvailability::class)));
        self::assertSame(['doctrine.dbal.shared_connection'], $this->arguments($container, DbalTenantDatabaseAvailability::class));
        self::assertFalse($container->hasDefinition(PhilTenancyDatabaseBundle::STORE_CONNECTION));
        self::assertSame(['doctrine.dbal.shared_connection', true, true], $this->arguments($container, StoreSchemaListener::class));
        self::assertSame([['event' => 'postGenerateSchema', 'connection' => 'shared']], $container->getDefinition(StoreSchemaListener::class)->getTag('doctrine.event_listener'));
    }

    public function testThePlacementsInTheTableAndTheSuspensionsInTheCachePool(): void
    {
        $container = $this->load([['placement' => 'dbal', 'availability' => 'cache']]);

        self::assertSame(DbalPlacementStore::class, $this->id($container->getAlias(TenantPlacement::class)));
        self::assertSame([PhilTenancyDatabaseBundle::STORE_CONNECTION], $this->arguments($container, DbalPlacementStore::class));
        self::assertSame(CacheTenantDatabaseAvailability::class, $this->id($container->getAlias(TenantDatabaseAvailability::class)));
        self::assertSame([PhilTenancyDatabaseBundle::AVAILABILITY_POOL], $this->arguments($container, CacheTenantDatabaseAvailability::class));
        self::assertFalse($container->hasDefinition(DbalTenantDatabaseAvailability::class));
        self::assertSame(['doctrine.dbal.default_connection', true, false], $this->arguments($container, StoreSchemaListener::class));
    }

    public function testNoStoreConnectionWithoutATable(): void
    {
        $container = $this->load([['availability' => 'cache']]);

        self::assertFalse($container->hasDefinition(PhilTenancyDatabaseBundle::STORE_CONNECTION));
        self::assertFalse($container->hasDefinition(StoreSchemaListener::class));
        self::assertSame(ConfiguredPlacements::class, $this->id($container->getAlias(TenantPlacement::class)));
    }

    public function testTheTableOfThePlacementsLeavesNoRoomForDedicatedTenants(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"dedicated" would be ignored');

        $this->load([['placement' => 'dbal', 'dedicated' => ['wholesale' => 'tenant_wholesale']]]);
    }

    public function testTheDedicatedTenantsBesideAnotherStore(): void
    {
        $container = $this->load([['placement' => 'app.placements', 'dedicated' => ['wholesale' => 'tenant_wholesale']]]);

        self::assertSame('app.placements', $this->id($container->getAlias(TenantPlacement::class)));
    }

    public function testTheStoreConnectionCannotFollowTheTenant(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The store connection follows the active tenant');

        $this->load([['connection' => 'tenants', 'store_connection' => 'tenants']]);
    }

    public function testAnEmptyAvailabilityIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['availability' => '']]);
    }

    public function testTheDedicatedTenantsAreCheckedAsTheContainerCompiles(): void
    {
        self::assertSame([['wholesale' => 'tenant_wholesale']], array_values($this->load([['dedicated' => ['wholesale' => 'tenant_wholesale']]])->getDefinition(ConfiguredPlacements::class)->getArguments()));

        $this->expectException(InvalidValue::class);

        $this->load([['dedicated' => ['wholesale' => 'Tenant Wholesale']]]);
    }

    public function testAConcurrencyOutOfRangeIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([['migrations' => ['concurrency' => 33]]]);
    }

    public function testTheAvailabilityPoolIsPrependedAndSharedByEveryTenant(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->namedExtension('framework'));
        $container->registerExtension($this->namedExtension('phil_tenancy'));

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_tenancy_database', ['availability' => 'cache']);

        $extension->prepend($container);

        self::assertSame([['cache' => ['pools' => [PhilTenancyDatabaseBundle::AVAILABILITY_POOL => ['adapter' => 'cache.app']]]]], $container->getExtensionConfig('framework'));
        self::assertSame([['cache' => ['global_pools' => [PhilTenancyDatabaseBundle::AVAILABILITY_POOL]]]], $container->getExtensionConfig('phil_tenancy'));
    }

    public function testNothingIsPrependedWithoutFrameworkBundle(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->namedExtension('phil_tenancy'));

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('phil_tenancy'));
    }

    public function testNothingIsPrependedForTheTableOfTheSuspensions(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->namedExtension('framework'));
        $container->registerExtension($this->namedExtension('phil_tenancy'));

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_tenancy_database', ['availability' => 'app.availability']);

        $extension->prepend($container);

        self::assertSame([], $container->getExtensionConfig('framework'));
        self::assertSame([], $container->getExtensionConfig('phil_tenancy'));
    }

    public function testThePoolIsPrependedWithoutTheTenancyBundleConfigured(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->namedExtension('framework'));

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('phil_tenancy_database', ['availability' => 'cache']);

        $extension->prepend($container);

        self::assertCount(1, $container->getExtensionConfig('framework'));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $this->extension()->load($configs, $container);

        return $container;
    }

    /**
     * The arguments of a service, each reference as the id it names.
     *
     * @return list<mixed>
     */
    private function arguments(ContainerBuilder $container, string $id): array
    {
        return array_map(static fn (mixed $argument): mixed => $argument instanceof Reference ? (string) $argument : $argument, array_values($container->getDefinition($id)->getArguments()));
    }

    /**
     * The arguments of the rollback, the connections as the ids they name, each checked to be skipped until created.
     *
     * @return list<mixed>
     */
    private function rollbackArguments(ContainerBuilder $container): array
    {
        $arguments = array_values($container->getDefinition(RollBackOpenTransactions::class)->getArguments());
        $connections = $arguments[0];
        self::assertInstanceOf(IteratorArgument::class, $connections);
        $arguments[0] = array_map(static function (mixed $reference): string {
            self::assertInstanceOf(Reference::class, $reference);
            self::assertSame(ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE, $reference->getInvalidBehavior());

            return (string) $reference;
        }, array_values($connections->getValues()));

        return array_map(static fn (mixed $argument): mixed => $argument instanceof Reference ? (string) $argument : $argument, $arguments);
    }

    private function id(mixed $reference): string
    {
        self::assertTrue($reference instanceof Reference || $reference instanceof Alias);

        return (string) $reference;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilTenancyDatabaseBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function namedExtension(string $alias): ExtensionInterface
    {
        return new class($alias) extends Extension {
            public function __construct(
                private readonly string $alias,
            ) {
            }

            #[Override]
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            #[Override]
            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
