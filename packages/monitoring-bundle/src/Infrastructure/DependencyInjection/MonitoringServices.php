<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Infrastructure\DependencyInjection;

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
use Phil\MonitoringBundle\Infrastructure\Http\HealthController;
use Phil\MonitoringBundle\Infrastructure\Http\MaintenanceListener;
use Phil\MonitoringBundle\Infrastructure\Http\QueuesController;
use Phil\MonitoringBundle\Infrastructure\Messenger\WatchHeartbeatsHandler;
use Phil\MonitoringBundle\Infrastructure\Scheduler\HeartbeatOnScheduledRun;
use Phil\MonitoringBundle\Infrastructure\Store\CacheHeartbeatStore;
use Phil\MonitoringBundle\Infrastructure\Store\CacheMaintenanceStore;
use Phil\MonitoringBundle\Infrastructure\Store\FileMaintenanceStore;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Scheduler\Event\PostRunEvent;

/**
 * The bundle's services, from its configuration. None keeps anything from one request to the next: every state is
 * read from its store when asked.
 */
final class MonitoringServices
{
    /**
     * The tag of the health checks; `PhilMonitoringBundle::HEALTH_CHECK_TAG` names it for applications.
     */
    public const string HEALTH_CHECK_TAG = 'phil_monitoring.health_check';

    /**
     * The maintenance listener runs after the router, for the route name, and after the firewall, for the roles.
     */
    public const int MAINTENANCE_LISTENER_PRIORITY = 7;

    private function __construct()
    {
    }

    /**
     * @param array{clock: string, cache_pool: string, health: array{token: ?string, database: list<string>, cache_pools: list<string>, messenger: array<string, ?int>, filesystems: array<string, array{path: string, min_free_megabytes: ?int}>, flysystem: array<string, string>}, maintenance: array{store: string, file: string, retry_after: int, allowed_ips: list<string>, allowed_roles: list<string>, allowed_routes: list<string>, template: string}, heartbeats: array{store: ?string, tasks: array<array-key, array{every: int, grace: int}>, lock: array{factory: string, ttl: int}, alerts: array{channels: list<string>, importance: string}}, queues: array{enabled: bool, role: string, failed_limit: int, layout: string}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $clock = new Reference($config['clock']);
        $pool = new Reference($config['cache_pool']);
        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $container->registerForAutoconfiguration(HealthCheck::class)->addTag(self::HEALTH_CHECK_TAG);

        self::heartbeats($container, $config['heartbeats'], $pool, $clock, $logger);
        self::checks($container, $config['health']);
        self::maintenance($container, $config['maintenance'], $pool, $clock);

        $container->register(HealthChecker::class, HealthChecker::class)
            ->setArguments([new TaggedIteratorArgument(self::HEALTH_CHECK_TAG), $clock, $logger]);
        $container->register(Monitoring::class, Monitoring::class)
            ->setArguments([new Reference(HealthChecker::class), new Reference(Heartbeats::class), new Reference(Maintenance::class)]);
        $container->setAlias(MonitoringStatus::class, Monitoring::class)->setPublic(true);
        $container->register(HealthController::class, HealthController::class)
            ->setArguments([new Reference(MonitoringStatus::class), $config['health']['token']])
            ->addTag('controller.service_arguments');

        foreach ([HealthCommand::class => [HealthChecker::class], HeartbeatCommand::class => [Heartbeats::class], WatchCommand::class => [DeadManSwitch::class, Heartbeats::class], MaintenanceCommand::class => [Maintenance::class]] as $command => $arguments) {
            $container->register($command, $command)
                ->setArguments(array_map(static fn (string $id): Reference => new Reference($id), $arguments))
                ->addTag('console.command');
        }

        if ($config['queues']['enabled']) {
            // The receivers and transport names are filled by MonitoringPass, which removes both services when the
            // application has no Messenger, Twig or security to serve them.
            $container->register(Queues::class, Queues::class)
                ->setArguments([null, [], [], new Reference('messenger.default_bus')]);
            $container->register(QueuesController::class, QueuesController::class)
                ->setArguments([
                    new Reference(Queues::class),
                    new Reference('security.authorization_checker'),
                    new Reference('security.csrf.token_manager'),
                    new Reference('twig'),
                    new Reference('router'),
                    $config['queues']['role'],
                    $config['queues']['failed_limit'],
                    $config['queues']['layout'],
                ])
                ->addTag('controller.service_arguments');
        }
    }

    /**
     * @param array{store: ?string, tasks: array<array-key, array{every: int, grace: int}>, lock: array{factory: string, ttl: int}, alerts: array{channels: list<string>, importance: string}} $config
     */
    private static function heartbeats(ContainerBuilder $container, array $config, Reference $pool, Reference $clock, Reference $logger): void
    {
        $store = $config['store'] ?? CacheHeartbeatStore::class;

        if (null === $config['store']) {
            $container->register(CacheHeartbeatStore::class, CacheHeartbeatStore::class)->setArguments([$pool]);
        }

        $container->setAlias(HeartbeatStore::class, $store);

        $tasks = [];

        foreach ($config['tasks'] as $name => $task) {
            $tasks[] = new Definition(ExpectedTask::class, [(string) $name, $task['every'], $task['grace']]);
        }

        $container->register(Heartbeats::class, Heartbeats::class)
            ->setArguments([$tasks, new Reference(HeartbeatStore::class), $clock]);
        $container->register(DeadManSwitch::class, DeadManSwitch::class)
            ->setArguments([
                new Reference(Heartbeats::class),
                new Reference(HeartbeatStore::class),
                new Reference($config['lock']['factory']),
                new Reference('notifier', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['alerts']['channels'],
                $config['alerts']['importance'],
                $logger,
                (float) $config['lock']['ttl'],
            ]);
        $container->register(WatchHeartbeatsHandler::class, WatchHeartbeatsHandler::class)
            ->setArguments([new Reference(DeadManSwitch::class)])
            ->addTag('messenger.message_handler', ['handles' => WatchHeartbeats::class]);

        if (class_exists(PostRunEvent::class)) {
            $container->register(HeartbeatOnScheduledRun::class, HeartbeatOnScheduledRun::class)
                ->setArguments([new Reference(Heartbeats::class), $logger])
                ->addTag('kernel.event_listener', ['event' => PostRunEvent::class]);
        }

        if ([] !== $tasks) {
            $container->register(HeartbeatsCheck::class, HeartbeatsCheck::class)
                ->setArguments([new Reference(Heartbeats::class)])
                ->addTag(self::HEALTH_CHECK_TAG);
        }
    }

    /**
     * @param array{token: ?string, database: list<string>, cache_pools: list<string>, messenger: array<string, ?int>, filesystems: array<string, array{path: string, min_free_megabytes: ?int}>, flysystem: array<string, string>} $config
     */
    private static function checks(ContainerBuilder $container, array $config): void
    {
        foreach ($config['database'] as $connection) {
            self::check($container, DatabaseCheck::class, [new Reference(\sprintf('doctrine.dbal.%s_connection', $connection)), 'database.'.$connection]);
        }

        foreach ($config['cache_pools'] as $pool) {
            self::check($container, CachePoolCheck::class, [new Reference($pool), $pool]);
        }

        foreach ($config['messenger'] as $transport => $maxMessages) {
            self::check($container, MessengerTransportCheck::class, [new Reference('messenger.transport.'.$transport), 'messenger.'.$transport, $maxMessages]);
        }

        foreach ($config['filesystems'] as $name => $filesystem) {
            self::check($container, FilesystemCheck::class, [$filesystem['path'], 'filesystem.'.$name, $filesystem['min_free_megabytes']]);
        }

        foreach ($config['flysystem'] as $name => $storage) {
            self::check($container, FlysystemCheck::class, [new Reference($storage), 'storage.'.$name]);
        }
    }

    /**
     * Registers one of the bundle's checks as `phil_monitoring.check.<name>`, its name its second argument.
     *
     * @param class-string<HealthCheck>            $class
     * @param array{0: mixed, 1: string, 2?: ?int} $arguments
     */
    private static function check(ContainerBuilder $container, string $class, array $arguments): void
    {
        $container->register('phil_monitoring.check.'.$arguments[1], $class)
            ->setArguments($arguments)
            ->addTag(self::HEALTH_CHECK_TAG);
    }

    /**
     * @param array{store: string, file: string, retry_after: int, allowed_ips: list<string>, allowed_roles: list<string>, allowed_routes: list<string>, template: string} $config
     */
    private static function maintenance(ContainerBuilder $container, array $config, Reference $pool, Reference $clock): void
    {
        if ('cache' === $config['store']) {
            $container->register(CacheMaintenanceStore::class, CacheMaintenanceStore::class)->setArguments([$pool, $clock, $config['retry_after']]);
            $container->setAlias(MaintenanceStore::class, CacheMaintenanceStore::class);
        } else {
            $container->register(FileMaintenanceStore::class, FileMaintenanceStore::class)->setArguments([$config['file'], $config['retry_after']]);
            $container->setAlias(MaintenanceStore::class, FileMaintenanceStore::class);
        }

        $container->register(Maintenance::class, Maintenance::class)
            ->setArguments([new Reference(MaintenanceStore::class), $clock, $config['retry_after']]);
        $container->register(MaintenanceListener::class, MaintenanceListener::class)
            ->setArguments([
                new Reference(MaintenanceStore::class),
                $config['allowed_ips'],
                $config['allowed_roles'],
                [...$config['allowed_routes'], HealthController::LIVE_ROUTE, HealthController::READY_ROUTE, HealthController::DETAILS_ROUTE],
                new Reference('security.authorization_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                new Reference('twig', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                $config['template'],
            ])
            ->addTag('kernel.event_listener', ['event' => 'kernel.request', 'priority' => self::MAINTENANCE_LISTENER_PRIORITY]);
    }
}
