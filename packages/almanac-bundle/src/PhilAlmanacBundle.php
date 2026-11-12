<?php

declare(strict_types=1);

namespace Phil\AlmanacBundle;

use DateTimeZone;
use Override;
use Phil\AlmanacBundle\Application\AlmanacFactory;
use Phil\AlmanacBundle\Application\ConfiguredDates;
use Phil\AlmanacBundle\Application\Definitions;
use Phil\AlmanacBundle\Application\Places;
use Phil\AlmanacBundle\Infrastructure\Doctrine\DoctrineStoredDateRepository;
use Phil\AlmanacBundle\Infrastructure\Form\StoredDateType;
use Phil\AlmanacBundle\Infrastructure\Twig\AlmanacExtension;
use Phil\AlmanacBundle\Infrastructure\Twig\SkyExtension;
use ReflectionClass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Twig\Attribute\AsTwigFunction;

/**
 * Computed dates in Symfony: the dates of the configuration, and — with `database: true` — those an administrator keeps
 * in `phil_almanac_date`, read by the almanac every time it is asked for.
 *
 * ```yaml
 * phil_almanac:
 *     timezone: America/Toronto
 *     dates:
 *         thanksgiving: { title: Action de grâce, rule: 2nd monday of october, tags: [holiday] }
 *         oktoberfest: { title: Oktoberfest, rule: saturday on or after 09-15, until: sunday on or after 10-03 }
 *     eclipses: { enabled: true }
 *     places:
 *         montreal: { latitude: 45.5017, longitude: -73.5673 }
 * ```
 *
 * Every configured date is read when the container is built: a rule the almanac cannot read stops the build.
 */
final class PhilAlmanacBundle extends AbstractBundle
{
    /** A build parameter, gone once the container is compiled. */
    private const string DOCTRINE = '.phil_almanac.doctrine';

    #[Override]
    protected string $extensionAlias = 'phil_almanac';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('timezone')
                    ->info('The time zone the dates are computed in, an IANA identifier: "America/Toronto".')
                    ->defaultValue('UTC')
                    ->validate()
                        ->ifTrue(static fn (mixed $timezone): bool => !\in_array($timezone, DateTimeZone::listIdentifiers(), true))
                        ->thenInvalid('The time zone is an IANA identifier: %s.')
                    ->end()
                ->end()
                ->booleanNode('database')
                    ->info('Also read the dates kept in phil_almanac_date, which replace configured dates of the same key. Needs Doctrine.')
                    ->defaultFalse()
                ->end()
                ->arrayNode('dates')
                    ->info('The computed dates, by key.')
                    ->useAttributeAsKey('key')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('title')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('rule')->info('When it starts: "2nd monday of october", "easter - 47 days", "full moon".')->isRequired()->cannotBeEmpty()->end()
                            ->scalarNode('until')->info('When it ends: the first date of this rule on or after the start.')->defaultNull()->end()
                            ->integerNode('days')->info('How many days it lasts, without until.')->defaultValue(1)->min(1)->max(366)->end()
                            ->booleanNode('all_day')->info('False shows a phase, a season or an eclipse at its instant.')->defaultTrue()->end()
                            ->arrayNode('tags')->scalarPrototype()->cannotBeEmpty()->end()->defaultValue([])->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('eclipses')
                    ->info('Declare the solar and lunar eclipses as dates, at their instant: solar-eclipse and lunar-eclipse.')
                    ->canBeEnabled()
                    ->children()
                        ->scalarNode('solar_title')->defaultValue('Solar eclipse')->cannotBeEmpty()->end()
                        ->scalarNode('lunar_title')->defaultValue('Lunar eclipse')->cannotBeEmpty()->end()
                        ->arrayNode('tags')->scalarPrototype()->cannotBeEmpty()->end()->defaultValue(['eclipse'])->end()
                    ->end()
                ->end()
                ->arrayNode('places')
                    ->info('The places sunrise, sunset and the twilights are computed for, by name.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->arrayPrototype()
                        ->children()
                            ->floatNode('latitude')->info('Decimal degrees, north positive.')->isRequired()->min(-90)->max(90)->end()
                            ->floatNode('longitude')->info('Decimal degrees, east positive.')->isRequired()->min(-180)->max(180)->end()
                            ->scalarNode('timezone')
                                ->info('The time zone its times are read in; phil_almanac.timezone by default.')
                                ->defaultNull()
                                ->validate()
                                    ->ifTrue(static fn (mixed $timezone): bool => !\in_array($timezone, DateTimeZone::listIdentifiers(), true))
                                    ->thenInvalid('The time zone is an IANA identifier: %s.')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                    ->defaultValue([])
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter(self::DOCTRINE, $builder->hasExtension('doctrine'));

        // The entity is mapped only for an almanac that keeps dates: otherwise a migration would make its table.
        if ($builder->hasExtension('doctrine') && $this->keepsDates($builder)) {
            $container->extension('doctrine', ['orm' => ['mappings' => ['PhilAlmanacBundle' => [
                'type' => 'attribute',
                'is_bundle' => false,
                'dir' => __DIR__.'/Domain/Model',
                'prefix' => 'Phil\AlmanacBundle\Domain\Model',
            ]]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{timezone: string, database: bool, dates: array<int|string, array{title: string, rule: string, until: ?string, days: int, all_day: bool, tags: list<string>}>, eclipses: array{enabled: bool, solar_title: string, lunar_title: string, tags: list<string>}, places: array<string, array{latitude: float|int, longitude: float|int, timezone: ?string}>} $config */
        $services = $container->services();
        $stored = null;

        if ($config['database']) {
            if (!$this->hasDoctrine($builder)) {
                throw new InvalidConfigurationException('phil_almanac.database reads the dates kept by Doctrine: register DoctrineBundle, or set it to false.');
            }

            $services->set(DoctrineStoredDateRepository::class)->args([new Reference('doctrine.orm.entity_manager')])->tag('kernel.reset', ['method' => 'reset']);

            foreach (new ReflectionClass(DoctrineStoredDateRepository::class)->getInterfaceNames() as $port) {
                $services->alias($port, DoctrineStoredDateRepository::class)->public();
            }

            $stored = new Reference(DoctrineStoredDateRepository::class);
        }

        $dates = array_merge(ConfiguredDates::eclipses($config['eclipses']), $config['dates']);
        ConfiguredDates::check($dates);
        $services->set(Definitions::class)->factory(ConfiguredDates::definitions(...))->args([$dates, $stored])->public();
        $services->set(Places::class)->args([array_map(static fn (array $place): array => [
            'latitude' => (float) $place['latitude'],
            'longitude' => (float) $place['longitude'],
            'timezone' => $place['timezone'] ?? $config['timezone'],
        ], $config['places'])])->public();
        $services->set(AlmanacFactory::class)->args([new Reference(Definitions::class), $config['timezone']])->public();
        $services->set(AlmanacFactory::ALMANAC)->factory([new Reference(AlmanacFactory::class), 'almanac'])->share(false)->public();
        $services->set(StoredDateType::class)->tag('form.type');

        if (class_exists(AsTwigFunction::class)) {
            $services->set(AlmanacExtension::class)
                ->args([new Reference(AlmanacFactory::class)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
            $services->set(SkyExtension::class)
                ->args([new Reference(Places::class), new Reference(AlmanacFactory::class)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }

    /**
     * Whether the configuration, as it stands before it is processed, keeps dates in the database: the last
     * `database` given wins, and anything but `false` — an environment variable — keeps them.
     */
    private function keepsDates(ContainerBuilder $builder): bool
    {
        $database = false;

        /** @var list<array<string, mixed>> $configs the configurations of the extension, as given */
        $configs = $builder->getExtensionConfig('phil_almanac');

        foreach ($configs as $config) {
            $database = \array_key_exists('database', $config) ? false !== $config['database'] : $database;
        }

        return $database;
    }

    /**
     * Whether DoctrineBundle is registered. Each extension loads in a container of its own, which has the parameters
     * but not the other extensions: `prependExtension()`, which sees them, leaves the answer in a build parameter.
     */
    private function hasDoctrine(ContainerBuilder $builder): bool
    {
        $parameters = $builder->getParameterBag()->all();

        return \array_key_exists(self::DOCTRINE, $parameters) ? true === $parameters[self::DOCTRINE] : $builder->hasExtension('doctrine');
    }
}
