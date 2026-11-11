<?php

declare(strict_types=1);

namespace Phil\ReportingBundle;

use DateTimeZone;
use Override;
use Phil\ReportingBundle\Application\Dashboard\ChartRendering;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\ReportingBundle\Infrastructure\DependencyInjection\KeyedServicesPass;
use Phil\ReportingBundle\Infrastructure\DependencyInjection\ReportingServices;
use Phil\SvgChart\ChartType;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Dashboards and reports for an application: metrics and series contributed by any bundle, dashboards as Twig
 * components drawn without a script, and reports exported, scheduled and delivered through a claim check.
 */
final class PhilReportingBundle extends AbstractBundle
{
    private const string KEY = '/^[A-Za-z0-9_.-]{1,64}$/D';

    #[Override]
    protected string $extensionAlias = 'phil_reporting';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KeyedServicesPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $presets = array_map(static fn (PeriodPreset $preset): string => $preset->value, PeriodPreset::cases());
        $children = $definition->rootNode()->children();

        $children
            ->scalarNode('timezone')
                ->info('The time zone the periods are counted in, whole days of it.')
                ->defaultValue('UTC')
                ->validate()
                    ->ifTrue(static fn (mixed $timezone): bool => !\is_string($timezone) || !\in_array($timezone, timezone_identifiers_list(DateTimeZone::ALL_WITH_BC), true))
                    ->thenInvalid('%s is not a time zone identifier.')
                ->end()
            ->end()
            ->arrayNode('chart')
                ->info('The size of the SVG charts, in pixels: they scale to their container.')
                ->addDefaultsIfNotSet()
                ->children()
                    ->integerNode('width')->defaultValue(640)->min(100)->max(4000)->end()
                    ->integerNode('height')->defaultValue(320)->min(100)->max(4000)->end()
                ->end()
            ->end()
            ->integerNode('max_rows')
                ->info('The most rows a report exports; the export stops with ReportTooLarge beyond.')
                ->defaultValue(100_000)
                ->min(1)
                ->max(10_000_000)
            ->end()
            ->enumNode('store')
                ->info('Where the jobs and the exports of the claim check wait: "directory", or "memory" for tests and the sync transport.')
                ->values(['directory', 'memory'])
                ->defaultValue('directory')
            ->end()
            ->scalarNode('directory')
                ->info('The directory of the "directory" store, readable by its owner only.')
                ->cannotBeEmpty()
                ->defaultValue('%kernel.project_dir%/var/phil_reporting')
            ->end()
            ->booleanNode('keep_delivered')
                ->info('Whether a delivered export stays in the export store.')
                ->defaultFalse()
            ->end()
            ->arrayNode('mail')
                ->info('The "mail" delivery: the file attached to an e-mail, sent by the mailer\'s transports.')
                ->canBeEnabled()
                ->children()
                    ->scalarNode('from')->info('The sender of the e-mails.')->defaultValue('')->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $mail): bool => true === $mail['enabled'] && '' === $mail['from'])
                    ->thenInvalid('The "mail" delivery needs its "from" address.')
                ->end()
            ->end()
            ->arrayNode('notification')
                ->info('The "notification" delivery: the report announced on the channels of Notifier.')
                ->canBeEnabled()
                ->children()
                    ->arrayNode('channels')->info('The channels, "chat/slack", "sms", "email"; the importance\'s when empty.')->scalarPrototype()->end()->end()
                ->end()
            ->end();

        $this->dashboards($children, $presets);
        $this->schedules($children, $presets);
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (interface_exists(AssetMapperInterface::class) && $builder->hasExtension('framework')) {
            $container->extension('framework', ['asset_mapper' => ['paths' => [\dirname(__DIR__).'/assets/styles' => 'phil-reporting']]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{timezone: string, chart: array{width: int, height: int}, max_rows: int, store: string, directory: string, keep_delivered: bool, mail: array{enabled: bool, from: string}, notification: array{enabled: bool, channels: list<string>}, dashboards: array<string, mixed>, schedules: array<string, mixed>} $config */
        ReportingServices::register($builder, $config);
    }

    /**
     * @param list<string> $presets
     */
    private function dashboards(NodeBuilder $children, array $presets): void
    {
        $children
            ->arrayNode('dashboards')
                ->info('The dashboards, by name.')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('title')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('role')->info('The role a viewer needs; everyone when null.')->defaultNull()->end()
                        ->enumNode('period')->values($presets)->defaultValue(PeriodPreset::Last30Days->value)->end()
                        ->arrayNode('periods')
                            ->info("The periods offered, in their order; the dashboard's own only when empty.")
                            ->enumPrototype()->values($presets)->end()
                        ->end()
                        ->arrayNode('widgets')
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('metric')->info('The key of a metric source.')->defaultNull()->end()
                                    ->scalarNode('series')->info('The key of a series source.')->defaultNull()->end()
                                    ->scalarNode('title')->info("The source's own title when empty.")->defaultValue('')->end()
                                    ->booleanNode('compare')->info('Whether a metric is compared with the period before.')->defaultFalse()->end()
                                    ->enumNode('chart')->values(array_map(static fn (ChartType $type): string => $type->value, ChartType::cases()))->defaultValue(ChartType::Bar->value)->end()
                                    ->enumNode('render')->values(array_map(static fn (ChartRendering $rendering): string => $rendering->value, ChartRendering::cases()))->defaultValue(ChartRendering::Svg->value)->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (array $widget): bool => (null === $widget['metric']) === (null === $widget['series']))
                                    ->thenInvalid('A widget names either a "metric" or a "series" source.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->always(static function (array $dashboard): array {
                            /** @var array{period: string, periods: list<string>} $dashboard */
                            $dashboard['periods'] = array_values(array_unique([] === $dashboard['periods'] ? [$dashboard['period']] : $dashboard['periods']));

                            return $dashboard;
                        })
                    ->end()
                    ->validate()
                        ->ifTrue(static fn (array $dashboard): bool => !\is_array($dashboard['periods']) || !\in_array($dashboard['period'], $dashboard['periods'], true))
                        ->thenInvalid("A dashboard's period is among the periods it offers.")
                    ->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $dashboards): bool => [] !== array_filter(array_keys($dashboards), static fn (int|string $name): bool => 1 !== preg_match(self::KEY, (string) $name)))
                    ->thenInvalid('A dashboard is named by 1 to 64 letters, digits, dots, dashes or underscores.')
                ->end()
            ->end();
    }

    /**
     * @param list<string> $presets
     */
    private function schedules(NodeBuilder $children, array $presets): void
    {
        $children
            ->arrayNode('schedules')
                ->info('The reports exported on a schedule, by name.')
                ->useAttributeAsKey('name')
                ->arrayPrototype()
                    ->children()
                        ->scalarNode('report')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('format')->defaultValue('csv')->cannotBeEmpty()->end()
                        ->scalarNode('every')->info('The frequency: "1 day", "1 week", "1 month".')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('from')->info('The first moment, "06:00" or "2026-11-02 06:00"; the start of the scheduler when null.')->defaultNull()->end()
                        ->enumNode('period')->values($presets)->defaultValue(PeriodPreset::LastMonth->value)->end()
                        ->scalarNode('delivery')->info('"mail", "notification" or a delivery of the application; the export stays in the store when null.')->defaultNull()->end()
                        ->arrayNode('recipients')->scalarPrototype()->end()->end()
                        ->arrayNode('parameters')->useAttributeAsKey('name')->scalarPrototype()->end()->end()
                    ->end()
                ->end()
                ->validate()
                    ->ifTrue(static fn (array $schedules): bool => [] !== array_filter(array_keys($schedules), static fn (int|string $name): bool => 1 !== preg_match(self::KEY, (string) $name)))
                    ->thenInvalid('A schedule is named by 1 to 64 letters, digits, dots, dashes or underscores.')
                ->end()
            ->end();
    }
}
