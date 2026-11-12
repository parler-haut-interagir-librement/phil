<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientConfiguration;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientServices;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\NativeAuthenticatorPass;
use Phil\IdentityClientBundle\PhilIdentityClientBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @phpstan-import-type Config from ClientConfiguration
 */
#[CoversClass(NativeAuthenticatorPass::class)]
#[CoversClass(ClientServices::class)]
#[CoversClass(PhilIdentityClientBundle::class)]
final class NativeAuthenticatorPassTest extends TestCase
{
    private const string AUTHENTICATOR = 'security.authenticator.oidc_login.main';

    public function testTheBundleRegistersThePass(): void
    {
        $container = new ContainerBuilder();
        new PhilIdentityClientBundle()->build($container);

        $passes = array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof NativeAuthenticatorPass);
        self::assertCount(1, $passes);
        self::assertContains(array_first($passes), $container->getCompilerPassConfig()->getBeforeOptimizationPasses());
    }

    public function testTheNativeServicesFollowTheFeatures(): void
    {
        $prefix = ClientServices::NATIVE_AUTHENTICATOR;
        self::assertSame('security.authenticator.oidc_login.', $prefix);
        self::assertSame(
            [$prefix.'shop', $prefix.'id_token.shop', $prefix.'signature_verifier.shop', $prefix.'backchannel_logout.shop', $prefix.'logout_token.shop', $prefix.'discovery.shop', $prefix.'ended_sessions.shop'],
            ClientServices::nativeServices($this->config(['firewall' => 'shop'])),
        );
        self::assertSame(
            [$prefix.'main', $prefix.'id_token.main', $prefix.'signature_verifier.main'],
            ClientServices::nativeServices($this->config(['back_channel_logout' => false])),
        );
        self::assertSame(
            [$prefix.'main', $prefix.'id_token.main', $prefix.'signature_verifier.main', $prefix.'token_refresh_listener.main', $prefix.'token_refresher.main'],
            ClientServices::nativeServices($this->config(['back_channel_logout' => false, 'refresh' => true])),
        );
    }

    public function testAContainerWithoutTheBundleIsLeftAlone(): void
    {
        $container = new ContainerBuilder();
        new NativeAuthenticatorPass()->process($container);

        $container->setParameter(NativeAuthenticatorPass::FIREWALL_PARAMETER, 'main');
        new NativeAuthenticatorPass()->process($container);

        $other = new ContainerBuilder();
        $other->setParameter(NativeAuthenticatorPass::SERVICES_PARAMETER, [self::AUTHENTICATOR]);
        new NativeAuthenticatorPass()->process($other);

        $malformed = new ContainerBuilder();
        $malformed->setParameter(NativeAuthenticatorPass::FIREWALL_PARAMETER, ['main']);
        $malformed->setParameter(NativeAuthenticatorPass::SERVICES_PARAMETER, [self::AUTHENTICATOR]);
        new NativeAuthenticatorPass()->process($malformed);

        $notAList = new ContainerBuilder();
        $notAList->setParameter(NativeAuthenticatorPass::FIREWALL_PARAMETER, 'main');
        $notAList->setParameter(NativeAuthenticatorPass::SERVICES_PARAMETER, self::AUTHENTICATOR);
        new NativeAuthenticatorPass()->process($notAList);

        self::assertFalse($container->has(self::AUTHENTICATOR));
    }

    public function testAHardenedAuthenticatorPasses(): void
    {
        $container = $this->container();
        $container->setAlias('security.authenticator.oidc_login.id_token.main', self::AUTHENTICATOR);

        new NativeAuthenticatorPass()->process($container);

        self::assertTrue($container->has(self::AUTHENTICATOR));
    }

    public function testAMissingNativeServiceStopsTheBuild(): void
    {
        $container = $this->container(['security.authenticator.oidc_login.token_refresher.main']);

        $this->expectExceptionObject(new LogicException('phil_identity_client needs the "security.authenticator.oidc_login.token_refresher.main" service of the oidc_login authenticator, which this version of symfony/security-bundle does not declare.'));

        new NativeAuthenticatorPass()->process($container);
    }

    public function testAnAuthenticatorWithoutPkceStopsTheBuild(): void
    {
        foreach ([['pkce_enabled' => false, 'pkce_method' => 'S256'], ['pkce_enabled' => true, 'pkce_method' => 'plain'], ['pkce_method' => 'S256'], ['pkce_enabled' => true], 'not options'] as $options) {
            $container = $this->container();
            $container->getDefinition(self::AUTHENTICATOR)->replaceArgument(8, $options);

            try {
                new NativeAuthenticatorPass()->process($container);
                self::fail('Built without PKCE.');
            } catch (LogicException $logicException) {
                self::assertSame('The oidc_login authenticator of firewall "main" must use PKCE with S256: phil_identity_client does not sign in without it.', $logicException->getMessage());
            }
        }
    }

    public function testAnAuthenticatorWithoutSignatureCheckStopsTheBuild(): void
    {
        $container = $this->container();
        $container->getDefinition(self::AUTHENTICATOR)->replaceArgument(10, null);

        $this->expectExceptionObject(new LogicException('The oidc_login authenticator of firewall "main" must check the signature of the ID token: phil_identity_client does not sign in without it.'));

        new NativeAuthenticatorPass()->process($container);
    }

    /**
     * @param list<string> $required the native services the bundle asks for besides the authenticator
     */
    private function container(array $required = []): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(self::AUTHENTICATOR, new Definition(null, array_fill(0, 14, null)))
            ->replaceArgument(8, ['pkce_enabled' => true, 'pkce_method' => 'S256'])
            ->replaceArgument(10, new Reference('security.authenticator.oidc_login.signature_verifier.main'));
        $container->setParameter(NativeAuthenticatorPass::FIREWALL_PARAMETER, 'main');
        $container->setParameter(NativeAuthenticatorPass::SERVICES_PARAMETER, [self::AUTHENTICATOR, ...$required]);

        return $container;
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return Config
     */
    private function config(array $overrides = []): array
    {
        $extension = new PhilIdentityClientBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        /** @var Config $config */
        $config = new Processor()->processConfiguration($configuration, [['issuer' => 'https://id.example.test', 'client_id' => 'site', 'client_secret' => 's', ...$overrides]]);

        return $config;
    }
}
