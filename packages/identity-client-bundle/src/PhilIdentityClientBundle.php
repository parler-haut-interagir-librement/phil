<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle;

use LogicException;
use Override;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientConfiguration;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientServices;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\FirewallComposer;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\NativeAuthenticatorPass;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Signs the users of a Symfony application in through any OpenID Connect provider:
 *
 * ```yaml
 * phil_identity_client:
 *     issuer: 'https://id.example.org'
 *     client_id: 'example-site'
 *     client_secret: '%env(OIDC_CLIENT_SECRET)%'
 * ```
 *
 * The flow itself is the `oidc_login` authenticator of Symfony; this bundle configures and hardens it.
 *
 * @phpstan-import-type Config from ClientConfiguration
 */
final class PhilIdentityClientBundle extends AbstractBundle
{
    #[Override]
    protected string $extensionAlias = 'phil_identity_client';

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new NativeAuthenticatorPass());
    }

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        ClientConfiguration::define($definition);
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $extension = $this->getContainerExtension();
        $configuration = $extension instanceof ConfigurationExtensionInterface ? $extension->getConfiguration([], $builder) : null;
        if (!$extension instanceof ExtensionInterface || !$configuration instanceof ConfigurationInterface) {
            throw new LogicException('The extension of the bundle has no configuration.');
        }

        /** @var Config $config */
        $config = new Processor()->processConfiguration($configuration, $builder->getExtensionConfig($extension->getAlias()));

        FirewallComposer::prepend($builder, $config);
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        ClientServices::register($container, $config);
    }
}
