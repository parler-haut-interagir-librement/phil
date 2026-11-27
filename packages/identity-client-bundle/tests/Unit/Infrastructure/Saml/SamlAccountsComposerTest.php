<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure\Saml;

use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientConfiguration;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\SamlAccountsComposer;
use Phil\IdentityClientBundle\PhilIdentityClientBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

/**
 * @phpstan-import-type Config from ClientConfiguration
 */
#[CoversClass(SamlAccountsComposer::class)]
#[CoversClass(ClientConfiguration::class)]
final class SamlAccountsComposerTest extends TestCase
{
    private const array REQUIRED = ['issuer' => 'https://id.example.test', 'client_id' => 'site', 'client_secret' => 's'];

    public function testTheSamlAccountsAreOffByDefault(): void
    {
        self::assertSame(['enabled' => false, 'verified_email_from' => []], $this->config()['saml']);
    }

    public function testTheTrustedIdentityProvidersAreKept(): void
    {
        $saml = $this->config(['saml' => ['verified_email_from' => ['acme', 'globex']]])['saml'];

        self::assertTrue($saml['enabled']);
        self::assertSame(['acme', 'globex'], $saml['verified_email_from']);
    }

    public function testTheServiceIsNamedInTheSamlConfiguration(): void
    {
        $container = $this->container();
        $container->loadFromExtension('phil_saml_sp', ['identity_providers' => []]);

        SamlAccountsComposer::prepend($container, $this->config(['saml' => true]));

        self::assertSame([['accounts' => 'phil_identity_client.saml_accounts'], ['identity_providers' => []]], $container->getExtensionConfig('phil_saml_sp'));
    }

    public function testNothingIsNamedWhenTheSamlAccountsAreOff(): void
    {
        $container = $this->container();
        $container->loadFromExtension('phil_saml_sp', ['identity_providers' => []]);

        SamlAccountsComposer::prepend($container, $this->config());

        self::assertSame([['identity_providers' => []]], $container->getExtensionConfig('phil_saml_sp'));
    }

    public function testNothingIsNamedWhenTheApplicationDoesNotConfigureSaml(): void
    {
        $container = $this->container();

        SamlAccountsComposer::prepend($container, $this->config(['saml' => true]));

        self::assertSame([], $container->getExtensionConfig('phil_saml_sp'));
    }

    public function testNothingIsNamedWithoutTheSamlBundle(): void
    {
        $container = new ContainerBuilder();

        SamlAccountsComposer::prepend($container, $this->config(['saml' => true]));

        self::assertFalse($container->hasExtension('phil_saml_sp'));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FakeSamlExtension());

        return $container;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return Config
     */
    private function config(array $overrides = []): array
    {
        $container = new ContainerBuilder();
        $extension = new PhilIdentityClientBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertNotNull($configuration);

        /** @var Config $config */
        $config = new Processor()->processConfiguration($configuration, [array_replace(self::REQUIRED, $overrides)]);

        return $config;
    }
}
