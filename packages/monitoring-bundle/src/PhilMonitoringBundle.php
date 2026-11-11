<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle;

use Override;
use Phil\MonitoringBundle\Infrastructure\DependencyInjection\MonitoringPass;
use Phil\MonitoringBundle\Infrastructure\DependencyInjection\MonitoringServices;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * The surveillance of a site, from its configuration:
 *
 * ```yaml
 * phil_monitoring:
 *     health:
 *         token: '%env(MONITORING_TOKEN)%'
 *         database: [default]
 *         cache_pools: [cache.app]
 *         messenger: { async: 100, failed: 0 }
 *     heartbeats:
 *         tasks:
 *             backup: { every: 86400, grace: 3600 }
 * ```
 *
 * The bundle wires the health checks — its own from the configuration and every service implementing `HealthCheck`,
 * tagged by autoconfiguration —, the endpoints of the uptime monitors, the maintenance mode, the heartbeats of the
 * scheduled tasks and their dead-man switch, the page of the Messenger queues, and the read model `MonitoringStatus`
 * another bundle shows.
 */
final class PhilMonitoringBundle extends AbstractBundle
{
    /**
     * The tag of the services implementing `HealthCheck`, run in the order they are registered.
     */
    public const string HEALTH_CHECK_TAG = MonitoringServices::HEALTH_CHECK_TAG;

    #[Override]
    protected string $extensionAlias = 'phil_monitoring';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $root = $definition->rootNode()->children();
        $root
            ->scalarNode('clock')->info('Service id of the PSR-20 clock.')->defaultValue('clock')->cannotBeEmpty()->end()
            ->scalarNode('cache_pool')->info('Service id of the PSR-6 pool of the heartbeats, and of the maintenance mode when its store is "cache".')->defaultValue('cache.app')->cannotBeEmpty()->end();

        $this->health($root->arrayNode('health')->addDefaultsIfNotSet()->children());
        $this->maintenance($root->arrayNode('maintenance')->addDefaultsIfNotSet()->children());
        $this->heartbeats($root->arrayNode('heartbeats')->addDefaultsIfNotSet()->children());
        $root->arrayNode('queues')
            ->canBeDisabled()
            ->children()
                ->scalarNode('role')->info('The role granted the page of the queues and its actions.')->defaultValue('ROLE_ADMIN')->cannotBeEmpty()->end()
                ->integerNode('failed_limit')->info('The failed messages listed per failure transport.')->min(1)->max(1000)->defaultValue(50)->end()
                ->scalarNode('layout')->info('The Twig layout the page extends; it defines the "title" and "content" blocks.')->defaultValue('@PhilMonitoring/layout.html.twig')->cannotBeEmpty()->end()
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{clock: string, cache_pool: string, health: array{token: ?string, database: list<string>, cache_pools: list<string>, messenger: array<string, ?int>, filesystems: array<string, array{path: string, min_free_megabytes: ?int}>, flysystem: array<string, string>}, maintenance: array{store: string, file: string, retry_after: int, allowed_ips: list<string>, allowed_roles: list<string>, allowed_routes: list<string>, template: string}, heartbeats: array{store: ?string, tasks: array<array-key, array{every: int, grace: int}>, lock: array{factory: string, ttl: int}, alerts: array{channels: list<string>, importance: string}}, queues: array{enabled: bool, role: string, failed_limit: int, layout: string}} $config */
        MonitoringServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new MonitoringPass());
    }

    private function health(NodeBuilder $health): void
    {
        $health
            ->scalarNode('token')->info('The bearer token of the detailed view; the view answers 404 while it is null.')->defaultNull()->end()
            ->arrayNode('database')->info('The DBAL connections to check, by name: "default".')->scalarPrototype()->end()->end()
            ->arrayNode('cache_pools')->info('Service ids of the PSR-6 pools to check: "cache.app", a pool on Valkey or Redis.')->scalarPrototype()->end()->end()
            ->arrayNode('messenger')
                ->info('The Messenger transports to count, each with the messages above which it is degraded, or null.')
                ->useAttributeAsKey('transport')
                ->normalizeKeys(false)
                ->variablePrototype()
                    ->validate()
                        ->ifTrue(static fn (mixed $max): bool => null !== $max && (!\is_int($max) || $max < 0))
                        ->thenInvalid('A threshold is a number of messages, 0 or more, or null; %s is not.')
                    ->end()
                ->end()
            ->end()
            ->arrayNode('filesystems')
                ->info('Local directories that must exist and be writable, by name.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->beforeNormalization()->ifString()->then(static fn (string $path): array => ['path' => $path])->end()
                    ->children()
                        ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                        ->integerNode('min_free_megabytes')->min(0)->defaultNull()->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('flysystem')
                ->info('Flysystem storages to check, by name: the service id of each.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->scalarPrototype()->end()
            ->end();
    }

    private function maintenance(NodeBuilder $maintenance): void
    {
        $maintenance
            ->enumNode('store')->info('Where the maintenance mode is kept: a flag file, or a key of cache_pool.')->values(['file', 'cache'])->defaultValue('file')->end()
            ->scalarNode('file')->info('The flag file of the "file" store.')->defaultValue('%kernel.project_dir%/var/maintenance.json')->cannotBeEmpty()->end()
            ->integerNode('retry_after')->info('The seconds visitors are told to wait, unless the command says otherwise.')->min(0)->defaultValue(300)->end()
            ->arrayNode('allowed_ips')->info('Addresses or CIDR networks that see the site during maintenance.')->scalarPrototype()->end()->end()
            ->arrayNode('allowed_roles')->info('Roles that see the site during maintenance.')->scalarPrototype()->end()->end()
            ->arrayNode('allowed_routes')->info('Routes served during maintenance, besides the health endpoints.')->scalarPrototype()->end()->end()
            ->scalarNode('template')->info('The Twig template of the maintenance page.')->defaultValue('@PhilMonitoring/maintenance.html.twig')->cannotBeEmpty()->end();
    }

    private function heartbeats(NodeBuilder $heartbeats): void
    {
        $heartbeats
            ->scalarNode('store')->info('Service id of a HeartbeatStore; null keeps the heartbeats in cache_pool.')->defaultNull()->end()
            ->arrayNode('tasks')
                ->info('The scheduled tasks expected to beat: every how many seconds, and with how many seconds of grace.')
                ->useAttributeAsKey('name')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->children()
                        ->integerNode('every')->isRequired()->min(1)->end()
                        ->integerNode('grace')->min(0)->defaultValue(0)->end()
                    ->end()
                ->end()
            ->end()
            ->arrayNode('lock')
                ->info('The lock each round of the dead-man switch takes per task, so that two workers never alert the same lateness twice.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->scalarNode('factory')->info('Service id of the LockFactory; its store must be shared by every worker and server.')->defaultValue('lock.factory')->cannotBeEmpty()->end()
                    ->integerNode('ttl')->info('The seconds a round holds the lock of a task at most.')->min(1)->max(3600)->defaultValue(30)->end()
                ->end()
            ->end()
            ->arrayNode('alerts')
                ->addDefaultsIfNotSet()
                ->children()
                    ->arrayNode('channels')->info('The Notifier channels of the alerts.')->scalarPrototype()->end()->defaultValue(['chat'])->end()
                    ->enumNode('importance')
                        ->values([Notification::IMPORTANCE_URGENT, Notification::IMPORTANCE_HIGH, Notification::IMPORTANCE_MEDIUM, Notification::IMPORTANCE_LOW])
                        ->defaultValue(Notification::IMPORTANCE_URGENT)
                    ->end()
                ->end()
            ->end();
    }
}
