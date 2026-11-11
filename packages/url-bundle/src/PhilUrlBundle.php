<?php

declare(strict_types=1);

namespace Phil\UrlBundle;

use Doctrine\DBAL\Types\Type;
use Override;
use Phil\UrlBundle\Infrastructure\Doctrine\UrlColumnType;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlType;
use Phil\UrlBundle\Infrastructure\Twig\UrlExtension;
use Phil\UrlBundle\Infrastructure\Validator\UrlOfTypeValidator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Twig\Attribute\AsTwigFilter;

/**
 * `phil/url` in a Symfony application: a field that detects the kind of an address among accepted enum sets, the
 * constraint `UrlOfType`, the column type `phil_url`, and addresses in Twig.
 */
final class PhilUrlBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_url';

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($builder->hasExtension('doctrine') && class_exists(Type::class)) {
            $container->extension('doctrine', ['dbal' => ['types' => [UrlColumnType::NAME => UrlColumnType::class]]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();
        $services->set(TypedUrlType::class)->tag('form.type');
        $services->set(UrlOfTypeValidator::class)->tag('validator.constraint_validator');

        if (class_exists(AsTwigFilter::class)) {
            $services->set(UrlExtension::class)
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
