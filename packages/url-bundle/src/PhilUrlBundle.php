<?php

declare(strict_types=1);

namespace Phil\UrlBundle;

use Doctrine\DBAL\Types\Type;
use Override;
use Phil\UrlBundle\Infrastructure\Doctrine\UrlColumnType;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlType;
use Phil\UrlBundle\Infrastructure\Twig\UrlExtension;
use Phil\UrlBundle\Infrastructure\Validator\UrlOfTypeValidator;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
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
                ->booleanNode('column_type')
                    ->info('Whether the column type phil_url is registered with DoctrineBundle: null registers it when doctrine/dbal and DoctrineBundle are installed, true requires them, false never registers it.')
                    ->defaultNull()
                ->end()
            ->end();
    }

    #[Override]
    public function boot(): void
    {
        if (!$this->dbalIsInstalled()) {
            return;
        }

        $container = $this->container;
        UrlColumnType::allowLocalhost($container instanceof ContainerInterface && $container->hasParameter('phil_url.allow_localhost') && true === $container->getParameter('phil_url.allow_localhost'));
    }

    /**
     * The column type's switch goes back to its default with the kernel: a kernel booted next, or a test after it, reads
     * its own configuration, never the last one's.
     */
    #[Override]
    public function shutdown(): void
    {
        if (!$this->dbalIsInstalled()) {
            return;
        }

        UrlColumnType::allowLocalhost(false);
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $wanted = $this->columnTypeSetting($builder);

        if (false === $wanted) {
            return;
        }

        $missing = match (true) {
            !$this->dbalIsInstalled() => 'doctrine/dbal',
            !$builder->hasExtension('doctrine') => 'doctrine/doctrine-bundle',
            default => null,
        };

        if (null !== $missing) {
            if (true === $wanted) {
                throw new InvalidConfigurationException(\sprintf('The column type phil_url needs the package "%s": run "composer require %s" and register DoctrineBundle, or set "phil_url.column_type" to null or false.', $missing, $missing));
            }

            return;
        }

        $container->extension('doctrine', ['dbal' => ['types' => [UrlColumnType::NAME => UrlColumnType::class]]]);
    }

    /**
     * `UrlColumnType` extends a class of `doctrine/dbal`, a package the bundle only suggests: nothing loads it unless
     * that package is installed.
     */
    private function dbalIsInstalled(): bool
    {
        return class_exists(Type::class);
    }

    /**
     * The key `column_type` as the application's configuration sets it, read before the extensions load: DoctrineBundle
     * learns of the column type by a prepended configuration.
     */
    private function columnTypeSetting(ContainerBuilder $builder): ?bool
    {
        $extension = $this->getContainerExtension();
        $configuration = $extension instanceof ConfigurationExtensionInterface ? $extension->getConfiguration([], $builder) : null;

        if (!$configuration instanceof ConfigurationInterface) {
            return null;
        }

        $config = new Processor()->processConfiguration($configuration, $builder->getExtensionConfig($this->extensionAlias));
        $wanted = $config['column_type'] ?? null;

        return \is_bool($wanted) ? $wanted : null;
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
