<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Types\TypeRegistry;
use Override;
use Phil\Measurement\ChannelConversions;
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitPreferences;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Doctrine\MeasurementType as MeasurementColumnType;
use Phil\MeasurementBundle\Infrastructure\Form\MeasurementType;
use Phil\MeasurementBundle\Infrastructure\Twig\MeasurementExtension;
use Phil\MeasurementBundle\Infrastructure\Validator\MeasurementRangeValidator;
use Phil\MeasurementBundle\Infrastructure\Validator\MeasurementUnitValidator;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\ConstraintValidator;
use Twig\Attribute\AsTwigFilter;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/**
 * `phil/measurement` in a Symfony application: the registry of units with the application's own, the unit of each
 * sales channel, the CLDR preferences and formatter and — when their packages are installed — the `MeasurementType`
 * form type, the `MeasurementRange` and `MeasurementUnit` constraints, the `format_measurement`,
 * `measurement_in_channel` and `preferred_units` Twig filters, and the `phil_measurement` column type.
 *
 * ```yaml
 * phil_measurement:
 *     units:
 *         mass:
 *             units:
 *                 '[gr]': { factor: '0.00006479891', cldr: mass-grain }
 *     channels:
 *         storefront: { mass: kg, length: cm }
 *         marketplace: { mass: '[lb_av]', length: '[in_i]' }
 * ```
 */
final class PhilMeasurementBundle extends AbstractBundle
{
    /** The public alias of the registry, which the column type is built with when the bundle boots. */
    public const string REGISTRY = 'phil_measurement.registry';

    #[Override]
    protected string $extensionAlias = 'phil_measurement';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $definition->rootNode()->children();

        $families = $children->arrayNode('units')
            ->info('Families and units added to those of the library, by family name: a new family names its "standard" unit.')
            ->useAttributeAsKey('name')
            ->normalizeKeys(false)
            ->arrayPrototype()
                ->children();
        $families
            ->scalarNode('standard')->info('The unit whose factor is 1, for a new family.')->defaultNull()->end()
            ->scalarNode('cldr')->info('The CLDR category of a new family, for its preferences.')->defaultNull()->end()
            ->scalarNode('difference')->info('The family that measures the differences of a new affine family.')->defaultNull()->end();
        $this->units($families->arrayNode('units')
            ->info('The units, by code: UCUM syntax, 1 to 64 printable ASCII characters.')
            ->isRequired()
            ->requiresAtLeastOneElement()
            ->useAttributeAsKey('code')
            ->normalizeKeys(false)
            ->arrayPrototype()
                ->children());

        $children
            ->arrayNode('channels')
                ->info('The unit each sales channel shows a family in, by channel then family.')
                ->useAttributeAsKey('channel')
                ->normalizeKeys(false)
                ->arrayPrototype()
                    ->useAttributeAsKey('family')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                ->end()
            ->end()
            ->booleanNode('doctrine_type')
                ->info('Registers the phil_measurement column type with Doctrine DBAL, when it is installed.')
                ->defaultTrue()
            ->end();
    }

    private function units(NodeBuilder $unit): void
    {
        $unit
            ->scalarNode('factor')->info('The factor to the standard unit: a decimal or a fraction of integers, such as "5/9".')->isRequired()->end()
            ->scalarNode('offset')->info('The offset of a temperature scale, added after the factor.')->defaultNull()->end()
            ->booleanNode('reciprocal')->info('A unit that varies the other way, such as miles per gallon.')->defaultFalse()->end()
            ->scalarNode('ucum')->info('The UCUM code exchanged with other systems, when it differs from the code.')->defaultNull()->end()
            ->scalarNode('cldr')->info('The CLDR key of the unit, for its patterns and preferences, such as "mass-grain".')->defaultNull()->end()
            ->scalarNode('difference')->info('The unit of differences of a unit of an affine family.')->defaultNull()->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{units: array<string, array<string, mixed>>, channels: array<string, array<string, string>>, doctrine_type: bool} $config */
        $definitions = $this->definitions($config['units']);

        // The definitions and the channels are checked once, when the container is built, rather than on a request.
        try {
            new ChannelConversions(UnitRegistry::standard()->withDefinitions($definitions), $config['channels']);
        } catch (MeasurementException $measurementException) {
            throw new InvalidConfigurationException('phil_measurement: '.$measurementException->getMessage(), previous: $measurementException);
        }

        $builder->setParameter('phil_measurement.doctrine_type', $config['doctrine_type']);
        $services = $container->services();

        $services->set('phil_measurement.standard_registry', UnitRegistry::class)
            ->factory([UnitRegistry::class, 'standard']);
        $services->set(UnitRegistry::class)
            ->factory([service('phil_measurement.standard_registry'), 'withDefinitions'])
            ->args([$definitions]);
        $services->alias(self::REGISTRY, UnitRegistry::class)->public();
        $services->set(ChannelConversions::class)
            ->args([service(UnitRegistry::class), $config['channels']]);
        $services->set(UnitPreferences::class)
            ->factory([UnitPreferences::class, 'cldr'])
            ->args([service(UnitRegistry::class)]);
        $services->set(UnitFormatter::class)
            ->factory([UnitFormatter::class, 'cldr']);

        if (class_exists(AbstractType::class)) {
            $services->set(MeasurementType::class)
                ->args([service(UnitRegistry::class), service(ChannelConversions::class), service(UnitFormatter::class)])
                ->tag('form.type');
        }

        if (class_exists(ConstraintValidator::class)) {
            $services->set(MeasurementRangeValidator::class)
                ->args([service(UnitRegistry::class)])
                ->tag('validator.constraint_validator');
            $services->set(MeasurementUnitValidator::class)
                ->tag('validator.constraint_validator');
        }

        if (class_exists(AsTwigFilter::class)) {
            $services->set(MeasurementExtension::class)
                ->args([service(UnitFormatter::class), service(ChannelConversions::class), service(UnitPreferences::class)])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }

    /**
     * Registers the column type with the application's registry. The type is built once, with the registry of the
     * configuration, which is the same for every request.
     */
    #[Override]
    public function boot(): void
    {
        if (!isset($this->container) || !class_exists(Configuration::class) || true !== $this->container->getParameter('phil_measurement.doctrine_type')) {
            return;
        }

        $registry = $this->container->get(self::REGISTRY);

        if (!$registry instanceof UnitRegistry) {
            return;
        }

        // A default configuration gives the global registry, which DoctrineBundle and the ORM read the types from.
        $types = new Configuration()->getTypeProvider();

        if (!$types instanceof TypeRegistry) {
            return;
        }

        $type = new MeasurementColumnType($registry);
        $types->has(MeasurementColumnType::NAME) ? $types->override(MeasurementColumnType::NAME, $type) : $types->register(MeasurementColumnType::NAME, $type);
    }

    /**
     * The configured definitions without the settings left out, which the registry would read as given.
     *
     * @param array<string, array<string, mixed>> $families
     *
     * @return array<string, array<string, mixed>>
     */
    private function definitions(array $families): array
    {
        $definitions = [];

        foreach ($families as $name => $family) {
            /** @var array<string, array<string, mixed>> $units */
            $units = $family['units'];
            $definitions[$name] = array_filter($family, static fn (mixed $value): bool => null !== $value);
            $definitions[$name]['units'] = array_map(
                static fn (array $unit): array => array_filter($unit, static fn (mixed $value): bool => null !== $value && false !== $value),
                $units,
            );
        }

        return $definitions;
    }
}
