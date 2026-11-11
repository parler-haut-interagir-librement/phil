<?php

declare(strict_types=1);

namespace Phil\AddressBundle;

use Doctrine\DBAL\Types\Type;
use Override;
use Phil\AddressBundle\Application\KnownCountries;
use Phil\AddressBundle\Infrastructure\Doctrine\AddressColumnType;
use Phil\AddressBundle\Infrastructure\Form\AddressType;
use Phil\AddressBundle\Infrastructure\Http\AddressFormatController;
use Phil\AddressBundle\Infrastructure\Twig\AddressExtension;
use Phil\AddressBundle\Infrastructure\Validator\ValidAddressValidator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Twig\Attribute\AsTwigFilter;

/**
 * `phil/address` in a Symfony application: the address form and its format endpoint, the constraint `ValidAddress`,
 * the column type `phil_address`, and addresses in Twig.
 */
final class PhilAddressBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_address';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_country')
                    ->info('The country of a new address, by its ISO 3166-1 alpha-2 code in capitals: "CA".')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $country): bool => null !== $country && !KnownCountries::has($country))
                        ->thenInvalid('The default country is the ISO 3166-1 alpha-2 code of a country, in capitals: %s.')
                    ->end()
                ->end()
                ->scalarNode('format_url')
                    ->info('The format endpoint with "__COUNTRY__" for the country, as the route phil_address_format gives it: "/address-format/__COUNTRY__"; null leaves the form without its Stimulus controller.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && class_exists(Type::class)) {
            $container->extension('doctrine', ['dbal' => ['types' => [AddressColumnType::NAME => AddressColumnType::class]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $translator = new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $services = $container->services();
        $services->set(AddressType::class)->args([$config['default_country'], $config['format_url'], $translator])->tag('form.type');
        $services->set(ValidAddressValidator::class)->tag('validator.constraint_validator');
        $services->set(AddressFormatController::class)->args([$translator])->tag('controller.service_arguments')->public();

        if (class_exists(AsTwigFilter::class)) {
            $services->set(AddressExtension::class)
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
