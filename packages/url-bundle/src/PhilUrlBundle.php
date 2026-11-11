<?php

declare(strict_types=1);

namespace Phil\UrlBundle;

use Doctrine\DBAL\Types\Type;
use Override;
use Phil\UrlBundle\Infrastructure\Doctrine\UrlColumnType;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlType;
use Phil\UrlBundle\Infrastructure\Twig\UrlExtension;
use Phil\UrlBundle\Infrastructure\Validator\UrlOfTypeValidator;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('allow_localhost')
                    ->info('Whether localhost and the names under .localhost are addresses: true in development and tests only.')
                    ->defaultFalse()
                ->end()
            ->end();
    }

    #[Override]
    public function boot(): void
    {
        $container = $this->container;
        UrlColumnType::allowLocalhost($container instanceof ContainerInterface && $container->hasParameter('phil_url.allow_localhost') && true === $container->getParameter('phil_url.allow_localhost'));
    }

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
        $container->parameters()->set('phil_url.allow_localhost', true === $config['allow_localhost']);
        $services = $container->services();
        $services->set(TypedUrlType::class)->arg('$allowLocalhost', '%phil_url.allow_localhost%')->tag('form.type');
        $services->set(UrlOfTypeValidator::class)->arg('$allowLocalhost', '%phil_url.allow_localhost%')->tag('validator.constraint_validator');

        if (class_exists(AsTwigFilter::class)) {
            $services->set(UrlExtension::class)
                ->arg('$allowLocalhost', '%phil_url.allow_localhost%')
                ->tag('twig.attribute_extension')
                ->tag('twig.runtime');
        }
    }
}
