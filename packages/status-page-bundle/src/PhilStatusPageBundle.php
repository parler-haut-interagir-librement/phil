<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle;

use Override;
use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\StatusPageBundle\Infrastructure\DependencyInjection\MonitoringPass;
use Phil\StatusPageBundle\Infrastructure\DependencyInjection\StatusPageServices;
use Phil\StatusPageBundle\Infrastructure\Twig\TwigPageRenderer;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The status page of a Symfony application, wired from its configuration:
 *
 * ```yaml
 * phil_status_page:
 *     title: Example status
 *     components:
 *         website: { name: Website, checks: [database] }
 *         api: { name: API, group: Platform, checks: [database, cache] }
 *     records:
 *         file: '%kernel.project_dir%/var/status/records.json'
 *     snapshot:
 *         base_url: 'https://status.example.org/'
 *         schedule: '5 minutes'
 *         targets:
 *             mirror: { type: directory, path: '/mnt/status' }
 * ```
 *
 * The page is served at `/status`, `/status.json` and `/status.atom`. Each component's state comes from the checks
 * of `phil/monitoring-bundle` when it is installed, else from the configuration; incidents and maintenances from the
 * configuration, a JSON file or a service of the application's own. A static snapshot of the page — HTML, JSON and
 * Atom — is pushed to each target by `phil:status-page:push`, or by the schedule `phil_status_page`. The templates
 * draw their parts with recipes of the PHIL UX kit — or the Shadcn kit — installed by the Symfony UX Toolkit.
 *
 * @phpstan-import-type Config from StatusPageServices
 */
final class PhilStatusPageBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_status_page';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
        $container->addCompilerPass(new MonitoringPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $statuses = array_map(static fn (ComponentStatus $status): string => $status->value, ComponentStatus::cases());

        $definition->rootNode()
            ->children()
                ->scalarNode('title')->info('The title of the page, of its feed and of its snapshot.')->defaultValue('Service status')->cannotBeEmpty()->end()
                ->enumNode('source')
                    ->info('Where the state of the components comes from: "monitoring", the checks of phil/monitoring-bundle; "declared", the "status" of each component; "auto", the first when it is installed, else the second.')
                    ->values(['auto', 'monitoring', 'declared'])
                    ->defaultValue('auto')
                ->end()
                ->scalarNode('source_service')->info('Service id of a ComponentStatuses of the application, instead of the source.')->defaultNull()->end()
                ->scalarNode('clock')->info('Service id of the PSR-20 clock the page is dated by.')->defaultValue('clock')->cannotBeEmpty()->end()
                ->integerNode('history_days')->info('The days of resolved incidents the page shows.')->min(0)->max(366)->defaultValue(90)->end()
                ->integerNode('cache_max_age')->info('The seconds browsers and shared caches may keep the page, its JSON and its feed; 0 makes them ask each time.')->min(0)->defaultValue(60)->end()
                ->scalarNode('layout')->info('The Twig layout the served page extends; it defines the "title" and "content" blocks.')->defaultValue(TwigPageRenderer::LAYOUT)->cannotBeEmpty()->end()
                ->scalarNode('kit_subdirectory')->info('Where the recipes of the kit are, under the components directory; the subdirectory of "ux_toolkit" when null. To set in an environment without the Toolkit.')->defaultNull()->end()
                ->arrayNode('monitoring')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('cache_ttl')->info('The seconds the states read from the checks are kept, so the checks do not run on every view; 0 runs them each time.')->min(0)->defaultValue(60)->end()
                        ->scalarNode('cache_pool')->info('Service id of the cache pool the states are kept in.')->defaultValue('cache.app')->cannotBeEmpty()->end()
                    ->end()
                ->end()
                ->append($this->components($statuses))
                ->arrayNode('records')
                    ->info('Where incidents and maintenances are kept: a service, else a JSON file, else the lists below.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('service')->info('Service id of a StatusRecords of the application.')->defaultNull()->end()
                        ->scalarNode('file')->info('A JSON file with the keys "incidents" and "maintenances", read on each page.')->defaultNull()->end()
                        ->variableNode('incidents')->info('The incidents, as RecordsReader reads them.')->defaultValue([])->end()
                        ->variableNode('maintenances')->info('The maintenances, as RecordsReader reads them.')->defaultValue([])->end()
                    ->end()
                ->end()
                ->append($this->snapshot())
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        StatusPageServices::register($builder, $config);
    }

    /**
     * @param list<string> $statuses
     */
    private function components(array $statuses): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('components');
        $node
            ->info('The components, by id, in the order the page shows them.')
            ->useAttributeAsKey('id')
            ->normalizeKeys(false)
            ->arrayPrototype()
                ->children()
                    ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('description')->defaultNull()->end()
                    ->scalarNode('group')->info('The heading the component is shown under.')->defaultNull()->end()
                    ->enumNode('status')->info('Its state when no check says otherwise.')->values($statuses)->defaultValue(ComponentStatus::Operational->value)->end()
                    ->arrayNode('checks')->info('The names of the checks of phil/monitoring-bundle it depends on.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                    ->arrayNode('tasks')->info('The names of the scheduled tasks of phil/monitoring-bundle it depends on.')->scalarPrototype()->cannotBeEmpty()->end()->end()
                ->end()
            ->end();

        return $node;
    }

    private function snapshot(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('snapshot');
        $node
            ->info('The static copy of the page pushed outside the monitored infrastructure.')
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('base_url')
                    ->info('The absolute address the snapshot is read at, such as https://status.example.org/; required with a target.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $url): bool => null !== $url && !self::isPublicAddress($url))
                        ->thenInvalid('The base address of the snapshot is an absolute http or https address on a public host.')
                    ->end()
                ->end()
                ->scalarNode('stylesheet')
                    ->info('The absolute address of a stylesheet the snapshot links to, instead of the few rules it carries.')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $url): bool => null !== $url && !self::isPublicAddress($url))
                        ->thenInvalid('The stylesheet of the snapshot is an absolute http or https address on a public host.')
                    ->end()
                ->end()
                ->scalarNode('schedule')->info('How often the schedule "phil_status_page" pushes a snapshot, such as "5 minutes"; no schedule when null.')->defaultNull()->end()
                ->arrayNode('targets')
                    ->info('Where the snapshot is published, by name.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->enumNode('type')->info('"directory", "flysystem", or "service" for a SnapshotPublisher of the application.')->values(['directory', 'flysystem', 'service'])->isRequired()->end()
                            ->scalarNode('path')->info('The directory of a "directory" target.')->defaultNull()->end()
                            ->scalarNode('storage')->info('Service id of the Flysystem storage of a "flysystem" target.')->defaultNull()->end()
                            ->scalarNode('prefix')->info('The directory in the storage of a "flysystem" target.')->defaultValue('')->end()
                            ->enumNode('visibility')->info('The visibility of the files of a "flysystem" target.')->values([null, 'public', 'private'])->defaultNull()->end()
                            ->scalarNode('service')->info('Service id of the publisher of a "service" target.')->defaultNull()->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static function (array $target): bool {
                                /** @var array{type: 'directory'|'flysystem'|'service', path: ?string, storage: ?string, service: ?string} $target */
                                return null === $target[['directory' => 'path', 'flysystem' => 'storage', 'service' => 'service'][$target['type']]];
                            })
                            ->thenInvalid('A "directory" target names its "path", a "flysystem" target its "storage", a "service" target its "service".')
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static function (array $snapshot): bool {
                    /** @var array{base_url: ?string, targets: array<string, mixed>} $snapshot */
                    return [] !== $snapshot['targets'] && null === $snapshot['base_url'];
                })
                ->thenInvalid('A snapshot names the address it is read at: set snapshot.base_url.')
            ->end()
            ->validate()
                ->ifTrue(static function (array $snapshot): bool {
                    /** @var array{schedule: ?string, targets: array<string, mixed>} $snapshot */
                    return null !== $snapshot['schedule'] && [] === $snapshot['targets'];
                })
                ->thenInvalid('A snapshot schedule pushes to targets: declare one under snapshot.targets.')
            ->end();

        return $node;
    }

    private static function isPublicAddress(mixed $url): bool
    {
        try {
            Url::fromString(\is_string($url) ? $url : '');
        } catch (UrlException) {
            return false;
        }

        return true;
    }
}
