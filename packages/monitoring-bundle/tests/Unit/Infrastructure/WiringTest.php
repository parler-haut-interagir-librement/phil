<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Infrastructure;

use Phil\MonitoringBundle\Application\Check\HeartbeatsCheck;
use Phil\MonitoringBundle\Application\Contract\MonitoringStatus;
use Phil\MonitoringBundle\Application\DeadManSwitch;
use Phil\MonitoringBundle\Application\HealthChecker;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Application\Maintenance;
use Phil\MonitoringBundle\Application\Message\WatchHeartbeats;
use Phil\MonitoringBundle\Application\Monitoring;
use Phil\MonitoringBundle\Application\Queue\Queues;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;
use Phil\MonitoringBundle\Domain\Contract\MaintenanceStore;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\ExpectedTask;
use Phil\MonitoringBundle\Infrastructure\Check\CachePoolCheck;
use Phil\MonitoringBundle\Infrastructure\Check\DatabaseCheck;
use Phil\MonitoringBundle\Infrastructure\Check\FilesystemCheck;
use Phil\MonitoringBundle\Infrastructure\Check\FlysystemCheck;
use Phil\MonitoringBundle\Infrastructure\Check\MessengerTransportCheck;
use Phil\MonitoringBundle\Infrastructure\Console\HealthCommand;
use Phil\MonitoringBundle\Infrastructure\Console\HeartbeatCommand;
use Phil\MonitoringBundle\Infrastructure\Console\MaintenanceCommand;
use Phil\MonitoringBundle\Infrastructure\Console\WatchCommand;
use Phil\MonitoringBundle\Infrastructure\DependencyInjection\MonitoringPass;
use Phil\MonitoringBundle\Infrastructure\DependencyInjection\MonitoringServices;
use Phil\MonitoringBundle\Infrastructure\Http\HealthController;
use Phil\MonitoringBundle\Infrastructure\Http\MaintenanceListener;
use Phil\MonitoringBundle\Infrastructure\Http\QueuesController;
use Phil\MonitoringBundle\Infrastructure\Messenger\WatchHeartbeatsHandler;
use Phil\MonitoringBundle\Infrastructure\Scheduler\HeartbeatOnScheduledRun;
use Phil\MonitoringBundle\Infrastructure\Store\CacheHeartbeatStore;
use Phil\MonitoringBundle\Infrastructure\Store\CacheMaintenanceStore;
use Phil\MonitoringBundle\Infrastructure\Store\FileMaintenanceStore;
use Phil\MonitoringBundle\PhilMonitoringBundle;
use Phil\MonitoringBundle\Tests\Unit\Support\StubCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\Event\PostRunEvent;

#[CoversClass(PhilMonitoringBundle::class)]
#[CoversClass(MonitoringServices::class)]
#[CoversClass(MonitoringPass::class)]
final class WiringTest extends TestCase
{
    public function testTheExtensionAlias(): void
    {
        self::assertSame('phil_monitoring', $this->extension()->getAlias());
    }

    public function testTheServicesOfTheDefaultConfiguration(): void
    {
        $container = $this->load([]);
        $clock = new Reference('clock');
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        self::assertEquals([new Reference('cache.app')], $container->getDefinition(CacheHeartbeatStore::class)->getArguments());
        self::assertSame(CacheHeartbeatStore::class, (string) $container->getAlias(HeartbeatStore::class));
        self::assertEquals([[], new Reference(HeartbeatStore::class), $clock], $container->getDefinition(Heartbeats::class)->getArguments());
        self::assertEquals([new Reference(Heartbeats::class), new Reference(HeartbeatStore::class), new Reference('lock.factory'), new Reference('notifier', ContainerInterface::NULL_ON_INVALID_REFERENCE), ['chat'], 'urgent', $logger, 30.0], $container->getDefinition(DeadManSwitch::class)->getArguments());
        self::assertEquals([new Reference(DeadManSwitch::class)], $container->getDefinition(WatchHeartbeatsHandler::class)->getArguments());
        self::assertSame([['handles' => WatchHeartbeats::class]], $container->getDefinition(WatchHeartbeatsHandler::class)->getTag('messenger.message_handler'));
        self::assertEquals([new Reference(Heartbeats::class), $logger], $container->getDefinition(HeartbeatOnScheduledRun::class)->getArguments());
        self::assertSame([['event' => PostRunEvent::class]], $container->getDefinition(HeartbeatOnScheduledRun::class)->getTag('kernel.event_listener'));
        self::assertFalse($container->hasDefinition(HeartbeatsCheck::class));

        self::assertSame(['%kernel.project_dir%/var/maintenance.json', 300], $container->getDefinition(FileMaintenanceStore::class)->getArguments());
        self::assertSame(FileMaintenanceStore::class, (string) $container->getAlias(MaintenanceStore::class));
        self::assertFalse($container->hasDefinition(CacheMaintenanceStore::class));
        self::assertEquals([new Reference(MaintenanceStore::class), $clock, 300], $container->getDefinition(Maintenance::class)->getArguments());
        self::assertEquals([
            new Reference(MaintenanceStore::class),
            [],
            [],
            ['phil_monitoring_live', 'phil_monitoring_ready', 'phil_monitoring_details'],
            new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('twig', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            '@PhilMonitoring/maintenance.html.twig',
        ], $container->getDefinition(MaintenanceListener::class)->getArguments());
        self::assertSame([['event' => 'kernel.request', 'priority' => 7]], $container->getDefinition(MaintenanceListener::class)->getTag('kernel.event_listener'));

        self::assertEquals([new TaggedIteratorArgument('phil_monitoring.health_check'), $clock, $logger], $container->getDefinition(HealthChecker::class)->getArguments());
        self::assertEquals([new Reference(HealthChecker::class), new Reference(Heartbeats::class), new Reference(Maintenance::class)], $container->getDefinition(Monitoring::class)->getArguments());
        self::assertSame(Monitoring::class, (string) $container->getAlias(MonitoringStatus::class));
        self::assertTrue($container->getAlias(MonitoringStatus::class)->isPublic());
        self::assertEquals([new Reference(MonitoringStatus::class), null], $container->getDefinition(HealthController::class)->getArguments());
        self::assertTrue($container->getDefinition(HealthController::class)->hasTag('controller.service_arguments'));

        foreach ([HealthCommand::class => [HealthChecker::class], HeartbeatCommand::class => [Heartbeats::class], WatchCommand::class => [DeadManSwitch::class, Heartbeats::class], MaintenanceCommand::class => [Maintenance::class]] as $command => $arguments) {
            $references = $container->getDefinition($command)->getArguments();
            self::assertContainsOnlyInstancesOf(Reference::class, $references);
            self::assertSame($arguments, array_map(strval(...), $references));
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'));
        }

        self::assertEquals([null, [], [], new Reference('messenger.default_bus')], $container->getDefinition(Queues::class)->getArguments());
        self::assertEquals([
            new Reference(Queues::class),
            new Reference('security.authorization_checker'),
            new Reference('security.csrf.token_manager'),
            new Reference('twig'),
            new Reference('router'),
            'ROLE_ADMIN',
            50,
            '@PhilMonitoring/layout.html.twig',
        ], $container->getDefinition(QueuesController::class)->getArguments());
        self::assertTrue($container->getDefinition(QueuesController::class)->hasTag('controller.service_arguments'));
        self::assertSame([], $container->findTaggedServiceIds('phil_monitoring.health_check'));
    }

    public function testAnApplicationCheckIsTaggedByAutoconfiguration(): void
    {
        $container = $this->load([]);

        self::assertSame(['phil_monitoring.health_check' => [[]]], $container->getAutoconfiguredInstanceof()[HealthCheck::class]->getTags());
        self::assertSame('phil_monitoring.health_check', PhilMonitoringBundle::HEALTH_CHECK_TAG);
        self::assertInstanceOf(HealthCheck::class, StubCheck::answering('a', CheckOutcome::up()));
    }

    public function testTheChecksOfTheConfiguration(): void
    {
        $container = $this->load(['health' => [
            'token' => 's3cret',
            'database' => ['default', 'audit'],
            'cache_pools' => ['cache.app', 'cache.redis'],
            'messenger' => ['async' => 100, 'failed' => 0, 'sync' => null],
            'filesystems' => ['uploads' => '/srv/uploads', 'exports' => ['path' => '/srv/exports', 'min_free_megabytes' => 512]],
            'flysystem' => ['media' => 'media.storage'],
        ]]);

        $checks = [
            'database.default' => [DatabaseCheck::class, [new Reference('doctrine.dbal.default_connection'), 'database.default']],
            'database.audit' => [DatabaseCheck::class, [new Reference('doctrine.dbal.audit_connection'), 'database.audit']],
            'cache.app' => [CachePoolCheck::class, [new Reference('cache.app'), 'cache.app']],
            'cache.redis' => [CachePoolCheck::class, [new Reference('cache.redis'), 'cache.redis']],
            'messenger.async' => [MessengerTransportCheck::class, [new Reference('messenger.transport.async'), 'messenger.async', 100]],
            'messenger.failed' => [MessengerTransportCheck::class, [new Reference('messenger.transport.failed'), 'messenger.failed', 0]],
            'messenger.sync' => [MessengerTransportCheck::class, [new Reference('messenger.transport.sync'), 'messenger.sync', null]],
            'filesystem.uploads' => [FilesystemCheck::class, ['/srv/uploads', 'filesystem.uploads', null]],
            'filesystem.exports' => [FilesystemCheck::class, ['/srv/exports', 'filesystem.exports', 512]],
            'storage.media' => [FlysystemCheck::class, [new Reference('media.storage'), 'storage.media']],
        ];

        self::assertSame(array_map(static fn (string $name): string => 'phil_monitoring.check.'.$name, array_keys($checks)), array_keys($container->findTaggedServiceIds('phil_monitoring.health_check')));

        foreach ($checks as $name => [$class, $arguments]) {
            $definition = $container->getDefinition('phil_monitoring.check.'.$name);
            self::assertSame($class, $definition->getClass());
            self::assertEquals($arguments, $definition->getArguments());
        }

        self::assertEquals([new Reference(MonitoringStatus::class), 's3cret'], $container->getDefinition(HealthController::class)->getArguments());
    }

    public function testTheHeartbeatsOfTheConfiguration(): void
    {
        $container = $this->load(['clock' => 'app.clock', 'cache_pool' => 'cache.monitoring', 'heartbeats' => [
            'tasks' => ['backup' => ['every' => 86_400, 'grace' => 3600], 'report' => ['every' => 60]],
            'alerts' => ['channels' => ['chat/slack', 'email'], 'importance' => 'high'],
        ]]);

        self::assertEquals([new Reference('cache.monitoring')], $container->getDefinition(CacheHeartbeatStore::class)->getArguments());
        self::assertEquals([
            [new Definition(ExpectedTask::class, ['backup', 86_400, 3600]), new Definition(ExpectedTask::class, ['report', 60, 0])],
            new Reference(HeartbeatStore::class),
            new Reference('app.clock'),
        ], $container->getDefinition(Heartbeats::class)->getArguments());
        $switch = $container->getDefinition(DeadManSwitch::class)->getArguments();
        self::assertEquals(new Reference('lock.factory'), $switch[2]);
        self::assertSame([['chat/slack', 'email'], 'high'], \array_slice($switch, 4, 2));
        self::assertSame(30.0, $switch[7]);
        self::assertEquals([new Reference(Heartbeats::class)], $container->getDefinition(HeartbeatsCheck::class)->getArguments());
        self::assertTrue($container->getDefinition(HeartbeatsCheck::class)->hasTag('phil_monitoring.health_check'));
    }

    public function testTheLockOfTheDeadManSwitch(): void
    {
        $switch = $this->load(['heartbeats' => ['lock' => ['factory' => 'app.shared_locks', 'ttl' => 3600]]])->getDefinition(DeadManSwitch::class);

        self::assertEquals(new Reference('app.shared_locks'), $switch->getArgument(2));
        self::assertSame(3600.0, $switch->getArgument(7));
        self::assertSame(1.0, $this->load(['heartbeats' => ['lock' => ['ttl' => 1]]])->getDefinition(DeadManSwitch::class)->getArgument(7));
    }

    public function testAHeartbeatStoreOfTheApplication(): void
    {
        $container = $this->load(['heartbeats' => ['store' => 'app.heartbeats']]);

        self::assertFalse($container->hasDefinition(CacheHeartbeatStore::class));
        self::assertSame('app.heartbeats', (string) $container->getAlias(HeartbeatStore::class));
    }

    public function testTheMaintenanceOfTheConfiguration(): void
    {
        $container = $this->load(['clock' => 'app.clock', 'cache_pool' => 'cache.shared', 'maintenance' => [
            'store' => 'cache',
            'retry_after' => 60,
            'allowed_ips' => ['10.0.0.0/8'],
            'allowed_roles' => ['ROLE_OPS'],
            'allowed_routes' => ['status'],
            'template' => 'maintenance.html.twig',
        ]]);

        self::assertEquals([new Reference('cache.shared'), new Reference('app.clock'), 60], $container->getDefinition(CacheMaintenanceStore::class)->getArguments());
        self::assertSame(CacheMaintenanceStore::class, (string) $container->getAlias(MaintenanceStore::class));
        self::assertFalse($container->hasDefinition(FileMaintenanceStore::class));
        self::assertEquals([new Reference(MaintenanceStore::class), new Reference('app.clock'), 60], $container->getDefinition(Maintenance::class)->getArguments());
        self::assertEquals([['10.0.0.0/8'], ['ROLE_OPS'], ['status', 'phil_monitoring_live', 'phil_monitoring_ready', 'phil_monitoring_details']], \array_slice($container->getDefinition(MaintenanceListener::class)->getArguments(), 1, 3));
        self::assertSame('maintenance.html.twig', $container->getDefinition(MaintenanceListener::class)->getArgument(6));

        $file = $this->load(['maintenance' => ['file' => '/srv/maintenance.json', 'retry_after' => 0]]);
        self::assertSame(['/srv/maintenance.json', 0], $file->getDefinition(FileMaintenanceStore::class)->getArguments());
    }

    public function testTheQueuesOfTheConfiguration(): void
    {
        $container = $this->load(['queues' => ['role' => 'ROLE_OPS', 'failed_limit' => 10, 'layout' => 'admin.html.twig']]);

        self::assertSame(['ROLE_OPS', 10, 'admin.html.twig'], \array_slice($container->getDefinition(QueuesController::class)->getArguments(), 5));

        $disabled = $this->load(['queues' => false]);
        self::assertFalse($disabled->hasDefinition(Queues::class));
        self::assertFalse($disabled->hasDefinition(QueuesController::class));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([
            ['maintenance' => ['store' => 'redis']],
            ['maintenance' => ['retry_after' => -1]],
            ['heartbeats' => ['tasks' => ['backup' => ['every' => 0]]]],
            ['heartbeats' => ['tasks' => ['backup' => ['every' => 60, 'grace' => -1]]]],
            ['heartbeats' => ['tasks' => ['backup' => []]]],
            ['heartbeats' => ['alerts' => ['importance' => 'whenever']]],
            ['heartbeats' => ['lock' => ['factory' => '']]],
            ['heartbeats' => ['lock' => ['ttl' => 0]]],
            ['heartbeats' => ['lock' => ['ttl' => 3601]]],
            ['health' => ['messenger' => ['async' => -1]]],
            ['health' => ['messenger' => ['async' => '5']]],
            ['health' => ['filesystems' => ['uploads' => ['min_free_megabytes' => 1]]]],
            ['health' => ['filesystems' => ['uploads' => ['path' => '/srv', 'min_free_megabytes' => -1]]]],
            ['queues' => ['failed_limit' => 0]],
            ['queues' => ['failed_limit' => 1001]],
            ['clock' => ''],
            ['cache_pool' => ''],
            ['maintenance' => ['file' => '']],
            ['maintenance' => ['template' => '']],
            ['queues' => ['role' => '']],
            ['queues' => ['layout' => '']],
        ] as $config) {
            $refusal = null;

            try {
                $this->load($config);
            } catch (InvalidConfigurationException $exception) {
                $refusal = $exception;
            }

            self::assertInstanceOf(InvalidConfigurationException::class, $refusal, json_encode($config, \JSON_THROW_ON_ERROR));
        }

        self::assertSame(1000, $this->load(['queues' => ['failed_limit' => 1000]])->getDefinition(QueuesController::class)->getArgument(6));
        self::assertSame(FileMaintenanceStore::class, (string) $this->load(['maintenance' => ['store' => 'file']])->getAlias(MaintenanceStore::class));
        self::assertSame(0, $this->load(['health' => ['filesystems' => ['uploads' => ['path' => '/srv', 'min_free_megabytes' => 0]]]])->getDefinition('phil_monitoring.check.filesystem.uploads')->getArgument(2));
        self::assertSame('urgent', $this->load(['heartbeats' => ['alerts' => ['importance' => 'urgent']]])->getDefinition(DeadManSwitch::class)->getArgument(5));
        self::assertSame('low', $this->load(['heartbeats' => ['alerts' => ['importance' => 'low']]])->getDefinition(DeadManSwitch::class)->getArgument(5));
        self::assertSame('medium', $this->load(['heartbeats' => ['alerts' => ['importance' => 'medium']]])->getDefinition(DeadManSwitch::class)->getArgument(5));
        $tasks = $this->load(['heartbeats' => ['tasks' => ['backup' => ['every' => 1, 'grace' => 0]]]])->getDefinition(Heartbeats::class)->getArgument(0);
        self::assertEquals([new Definition(ExpectedTask::class, ['backup', 1, 0])], $tasks);
        self::assertSame(1, $this->load(['queues' => ['failed_limit' => 1]])->getDefinition(QueuesController::class)->getArgument(6));
    }

    public function testTheKeysOfTheConfigurationAreKeptAsWritten(): void
    {
        $container = $this->load(['health' => ['messenger' => ['high-priority' => 5], 'filesystems' => ['user-uploads' => '/srv'], 'flysystem' => ['public-media' => 'media']], 'heartbeats' => ['tasks' => ['nightly-backup' => ['every' => 60], '2026' => ['every' => 60]]]]);

        self::assertTrue($container->hasDefinition('phil_monitoring.check.messenger.high-priority'));
        self::assertTrue($container->hasDefinition('phil_monitoring.check.filesystem.user-uploads'));
        self::assertTrue($container->hasDefinition('phil_monitoring.check.storage.public-media'));
        $tasks = $container->getDefinition(Heartbeats::class)->getArgument(0);
        self::assertIsArray($tasks);
        self::assertInstanceOf(Definition::class, $tasks[0]);
        self::assertSame('nightly-backup', $tasks[0]->getArgument(0));
        self::assertInstanceOf(Definition::class, $tasks[1]);
        self::assertSame('2026', $tasks[1]->getArgument(0));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilMonitoringBundle()->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof MonitoringPass);
        self::assertCount(1, $passes);
    }

    public function testThePassGivesTheQueuesTheirTransports(): void
    {
        $container = $this->load([]);
        $container->register('messenger.default_bus');
        $container->register('messenger.transport.async')->addTag('messenger.receiver', ['alias' => 'async', 'is_failure_transport' => false]);
        $container->register('messenger.transport.failed')->addTag('messenger.receiver', ['alias' => 'failed', 'is_failure_transport' => true]);
        $container->register('messenger.transport.legacy')->addTag('messenger.receiver', ['alias' => 'legacy']);
        $container->register('app.receiver')->addTag('messenger.receiver');
        $container->register('app.other_receiver')->addTag('messenger.receiver')->addTag('messenger.receiver', ['alias' => 'other']);
        foreach (['twig', 'router', 'security.authorization_checker', 'security.csrf.token_manager'] as $id) {
            $container->register($id);
        }

        new MonitoringPass()->process($container);

        $queues = $container->getDefinition(Queues::class);
        self::assertSame(['async', 'failed', 'legacy', 'other'], $queues->getArgument(1));
        self::assertSame(['failed'], $queues->getArgument(2));
        $locator = $queues->getArgument(0);
        self::assertInstanceOf(Reference::class, $locator);
        $definition = $container->getDefinition((string) $locator);
        self::assertSame(ServiceLocator::class, $definition->getClass());
        $services = $definition->getArgument(0);
        self::assertIsArray($services);
        self::assertSame(['async', 'failed', 'legacy', 'other'], array_keys($services));
        self::assertEquals(new ServiceClosureArgument(new Reference('messenger.transport.failed')), $services['failed']);
        self::assertTrue($container->hasDefinition(QueuesController::class));
    }

    public function testThePassRemovesTheQueuesWithoutMessenger(): void
    {
        $container = $this->load([]);
        new MonitoringPass()->process($container);

        self::assertFalse($container->hasDefinition(Queues::class));
        self::assertFalse($container->hasDefinition(QueuesController::class));
    }

    public function testThePassRemovesThePageWithoutWhatItNeeds(): void
    {
        foreach (['twig', 'router', 'security.authorization_checker', 'security.csrf.token_manager'] as $missing) {
            $container = $this->load([]);
            $container->setAlias('messenger.default_bus', 'app.bus');
            $container->register('app.bus');

            foreach (array_diff(['twig', 'router', 'security.authorization_checker', 'security.csrf.token_manager'], [$missing]) as $id) {
                $container->register($id);
            }

            new MonitoringPass()->process($container);

            self::assertTrue($container->hasDefinition(Queues::class));
            self::assertFalse($container->hasDefinition(QueuesController::class), $missing);
        }
    }

    public function testThePassLeavesDisabledQueuesAlone(): void
    {
        $container = $this->load(['queues' => false]);
        $container->register(QueuesController::class);

        new MonitoringPass()->process($container);

        self::assertTrue($container->hasDefinition(QueuesController::class));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', []);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilMonitoringBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
