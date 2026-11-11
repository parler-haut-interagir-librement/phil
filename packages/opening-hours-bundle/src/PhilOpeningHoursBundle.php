<?php

declare(strict_types=1);

namespace Phil\OpeningHoursBundle;

use DateTimeZone;
use Doctrine\DBAL\Types\Type;
use Override;
use Phil\OpeningHoursBundle\Infrastructure\Doctrine\OpeningHoursColumnType;
use Phil\OpeningHoursBundle\Infrastructure\Form\OpeningHoursType;
use Phil\OpeningHoursBundle\Infrastructure\Twig\OpeningHoursExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFunction;

/**
 * `phil/opening-hours` in a Symfony application: the hours form, the column type `phil_opening_hours`, and hours in
 * Twig.
 */
final class PhilOpeningHoursBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_opening_hours';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_timezone')
                    ->info('The time zone new hours are kept in, an IANA identifier: "America/Toronto".')
                    ->defaultValue('UTC')
                    ->validate()
                        ->ifTrue(static fn (mixed $timezone): bool => !\in_array($timezone, DateTimeZone::listIdentifiers(), true))
                        ->thenInvalid('The default time zone is an IANA identifier: %s.')
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && class_exists(Type::class)) {
            $container->extension('doctrine', ['dbal' => ['types' => [OpeningHoursColumnType::NAME => OpeningHoursColumnType::class]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->set(OpeningHoursType::class)->args([$config['default_timezone']])->tag('form.type');

        if (class_exists(AsTwigFunction::class)) {
            $services->set(OpeningHoursExtension::class)
                ->args([new Reference('clock')])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
