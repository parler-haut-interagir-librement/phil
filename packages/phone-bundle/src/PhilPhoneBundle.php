<?php

declare(strict_types=1);

namespace Phil\PhoneBundle;

use Doctrine\DBAL\Types\Type;
use Override;
use Phil\PhoneBundle\Application\Regions;
use Phil\PhoneBundle\Infrastructure\Doctrine\PhoneNumberColumnType;
use Phil\PhoneBundle\Infrastructure\Form\PhoneType;
use Phil\PhoneBundle\Infrastructure\Twig\PhoneExtension;
use Phil\PhoneBundle\Infrastructure\Validator\ValidPhoneNumberValidator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Attribute\AsTwigFilter;

/**
 * `phil/phone` in a Symfony application: a field that reads a number in a default country, the constraint
 * `ValidPhoneNumber`, the column type `phil_phone`, and numbers in Twig.
 */
final class PhilPhoneBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_phone';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('default_region')
                    ->info('The country, by its ISO 3166-1 alpha-2 code, a number typed without its country code is read in: "CA", "FR".')
                    ->defaultNull()
                    ->validate()
                        ->ifTrue(static fn (mixed $region): bool => null !== $region && !Regions::isCountry($region))
                        ->thenInvalid('The default region is the ISO 3166-1 alpha-2 code of a country with a numbering plan, in capitals: %s.')
                    ->end()
                ->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && class_exists(Type::class)) {
            $container->extension('doctrine', ['dbal' => ['types' => [PhoneNumberColumnType::NAME => PhoneNumberColumnType::class]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $region = $config['default_region'];
        $services = $container->services();
        $services->set(PhoneType::class)->args([$region])->tag('form.type');
        $services->set(ValidPhoneNumberValidator::class)->args([$region])->tag('validator.constraint_validator');

        if (class_exists(AsTwigFilter::class)) {
            $services->set(PhoneExtension::class)
                ->args([$region])
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
