<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\ClientConfiguration;
use Phil\IdentityClientBundle\Infrastructure\DependencyInjection\FirewallComposer;
use Phil\IdentityClientBundle\PhilIdentityClientBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

/**
 * @phpstan-import-type Config from ClientConfiguration
 */
#[CoversClass(ClientConfiguration::class)]
#[CoversClass(FirewallComposer::class)]
final class FirewallComposerTest extends TestCase
{
    private const array REQUIRED = ['issuer' => 'https://id.example.test', 'client_id' => 'site', 'client_secret' => 's'];

    public function testTheSecurityDefaultsArePinned(): void
    {
        $authenticator = FirewallComposer::authenticator($this->config());

        self::assertSame('sub', $authenticator['user_identifier_claim']);
        self::assertSame(['required' => true, 'algorithms' => ['RS256']], $authenticator['id_token_signature']);
        self::assertSame(['enabled' => true, 'method' => 'S256'], $authenticator['pkce']);
        self::assertSame('id_token', $authenticator['user_data_source']);
        self::assertSame(['client_secret_basic' => 's'], $authenticator['client_authentication']);
        self::assertSame(5, $authenticator['allowed_time_drift']);
        self::assertSame('https://id.example.test', $authenticator['provider_uri']);
        self::assertSame('site', $authenticator['client_id']);
        self::assertSame(['openid'], $authenticator['scope']);
        self::assertSame('/oidc/callback', $authenticator['check_path']);
        self::assertSame('/oidc/start', $authenticator['start_path']);
        self::assertSame('/', $authenticator['default_target_path']);
        self::assertSame('/', $authenticator['failure_path']);
        self::assertSame(['enabled' => false, 'leeway' => 30], $authenticator['refresh_access_token']);
        self::assertFalse($authenticator['enable_end_session']);
        self::assertSame('/', $authenticator['post_logout_redirect_path']);
        self::assertSame(['enabled' => true, 'path' => '/oidc/back-channel-logout', 'cache' => 'cache.security', 'lifetime' => 86400], $authenticator['backchannel_logout']);
        self::assertArrayNotHasKey('max_age', $authenticator);
        self::assertArrayNotHasKey('discovery_uri', $authenticator);
        self::assertArrayNotHasKey('http_client', $authenticator);
    }

    public function testTheOptionalsArePassedOn(): void
    {
        $authenticator = FirewallComposer::authenticator($this->config(['discovery_uri' => 'https://id.example.test/d', 'http_client' => 'c', 'elevation' => ['max_age' => 0]]));

        self::assertSame('https://id.example.test/d', $authenticator['discovery_uri']);
        self::assertSame('c', $authenticator['http_client']);
        self::assertSame(0, $authenticator['max_age']);
    }

    public function testTheEndedSessionsOutliveTheCap(): void
    {
        $authenticator = FirewallComposer::authenticator($this->config(['session' => ['max_lifetime' => 90000]]));

        self::assertIsArray($authenticator['backchannel_logout']);
        self::assertSame(90000, $authenticator['backchannel_logout']['lifetime']);
    }

    public function testBackChannelLogoutCanBeOff(): void
    {
        self::assertSame(['enabled' => false], FirewallComposer::authenticator($this->config(['back_channel_logout' => false]))['backchannel_logout']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function clientAuthentications(): iterable
    {
        yield 'post' => [['client_secret_transport' => 'post'], ['client_secret_post' => 's']];
        yield 'key' => [['client_secret' => null, 'client_key' => '{}', 'client_key_algorithm' => 'ES256'], ['private_key_jwt' => ['key' => '{}', 'algorithm' => 'ES256']]];
        yield 'public' => [['client_secret' => null, 'public_client' => true], ['none' => true]];
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $expected
     */
    #[DataProvider('clientAuthentications')]
    public function testTheClientAuthentication(array $config, array $expected): void
    {
        self::assertSame($expected, FirewallComposer::authenticator($this->config($config))['client_authentication']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalid(): iterable
    {
        yield 'two credentials' => [['client_key' => '{}']];
        yield 'none' => [['client_secret' => null]];
        yield 'public and a secret' => [['public_client' => true]];
        yield 'no signature' => [['signature_algorithm' => 'none']];
        yield 'a shared-key algorithm' => [['signature_algorithm' => 'HS256']];
        yield 'an origin with a path' => [['return_to' => ['origins' => ['https://a.example.test/x']]]];
        yield 'a plain origin' => [['return_to' => ['origins' => ['http://a.example.test']]]];
        yield 'an empty level' => [['elevation' => ['levels' => ['x' => []]]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testInvalidConfiguration(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->config($config);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function belowTheirMinimum(): iterable
    {
        yield 'time drift' => [['allowed_time_drift' => -1]];
        yield 'session cap' => [['session' => ['max_lifetime' => -1]]];
        yield 'replay ttl' => [['back_channel_logout' => ['replay_ttl' => 0]]];
        yield 'global max age' => [['elevation' => ['max_age' => -1]]];
        yield 'retry after' => [['elevation' => ['retry_after' => -1]]];
        yield 'level max age' => [['elevation' => ['levels' => ['x' => ['max_age' => -1]]]]];
        yield 'leeway' => [['refresh' => ['leeway' => -1]]];
        yield 'wait timeout' => [['refresh' => ['wait_timeout' => -1.0]]];
        yield 'lock ttl' => [['refresh' => ['lock_ttl' => 0.5]]];
        yield 'outcome ttl' => [['refresh' => ['outcome_ttl' => 0]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('belowTheirMinimum')]
    public function testValuesBelowTheirMinimumAreRefused(array $config): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->config($config);
    }

    public function testValuesAtTheirMinimumAreAccepted(): void
    {
        $config = $this->config([
            'allowed_time_drift' => 0,
            'client_secret_transport' => 'basic',
            'claims_source' => 'id_token',
            'session' => ['max_lifetime' => 0],
            'back_channel_logout' => ['replay_ttl' => 1],
            'elevation' => ['max_age' => 0, 'retry_after' => 0, 'levels' => ['x' => ['max_age' => 0], 'y' => ['acr' => 'a']]],
            'refresh' => ['leeway' => 0, 'wait_timeout' => 0.0, 'lock_ttl' => 1.0, 'outcome_ttl' => 1],
        ]);

        self::assertSame(0, $config['allowed_time_drift']);
        self::assertSame(1, $config['back_channel_logout']['replay_ttl']);
        self::assertSame(0, $config['elevation']['retry_after']);
        self::assertSame(['x', 'y'], array_keys($config['elevation']['levels']));
        self::assertSame(['acr' => 'a', 'max_age' => null], $config['elevation']['levels']['y']);
        self::assertSame(0, $config['refresh']['leeway']);
        self::assertSame(1.0, $config['refresh']['lock_ttl']);
        self::assertSame(1, $config['refresh']['outcome_ttl']);
    }

    public function testTheDefaultsAreTheDocumentedOnes(): void
    {
        $config = $this->config();

        self::assertSame(60, $config['elevation']['retry_after']);
        self::assertSame(['enabled' => false, 'leeway' => 30, 'wait_timeout' => 10.0, 'lock_ttl' => 30.0, 'outcome_ttl' => 120, 'cache' => 'cache.security'], $config['refresh']);
        self::assertSame(43200, $config['session']['max_lifetime']);
        self::assertSame(['keep_access_token' => false, 'keep_id_token' => false], $config['tokens']);
    }

    public function testDashesInNamesAreKept(): void
    {
        $config = $this->config(['authorization_params' => ['a-b' => 'x'], 'elevation' => ['levels' => ['two-factor' => ['acr' => 'a']]]]);

        self::assertSame(['a-b' => 'x'], $config['authorization_params']);
        self::assertSame(['two-factor'], array_keys($config['elevation']['levels']));
    }

    public function testAnOriginFromAnEnvironmentVariableIsCheckedAtRuntime(): void
    {
        self::assertSame(['%env(ORIGIN)%'], $this->config(['return_to' => ['origins' => ['%env(ORIGIN)%']]])['return_to']['origins']);
    }

    public function testTheCapCanBeNull(): void
    {
        self::assertSame(0, $this->config(['session' => ['max_lifetime' => null]])['session']['max_lifetime']);
    }

    public function testAnUndeclaredFirewallIsAnError(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FakeSecurityExtension());
        $container->prependExtensionConfig('security', ['firewalls' => ['other' => []]]);

        try {
            FirewallComposer::prepend($container, $this->config());
            self::fail('The firewall is not declared.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_identity_client.firewall names "main", which security.firewalls does not declare.', $invalidConfigurationException->getMessage());
        }
    }

    public function testASessionKeptAcrossTheSignInIsRefused(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FakeSecurityExtension());
        $container->loadFromExtension('security', ['firewalls' => ['main' => []]]);
        $container->loadFromExtension('security', ['session_fixation_strategy' => 'none']);

        try {
            FirewallComposer::prepend($container, $this->config());
            self::fail('The session identifier would survive the sign-in.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('security.session_fixation_strategy is "none": phil_identity_client needs the session identifier changed at sign-in; use "migrate" or "invalidate".', $invalidConfigurationException->getMessage());
        }
    }

    public function testTheOtherSessionStrategiesAreAccepted(): void
    {
        foreach (['migrate', 'invalidate'] as $strategy) {
            $container = new ContainerBuilder();
            $container->registerExtension(new FakeSecurityExtension());
            $container->loadFromExtension('security', ['session_fixation_strategy' => $strategy, 'firewalls' => ['main' => []]]);

            FirewallComposer::prepend($container, $this->config());

            self::assertCount(2, $container->getExtensionConfig('security'));
        }
    }

    public function testTheFirstBlockNamesEveryFirewallAndProviderInOrder(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new FakeSecurityExtension());
        $container->loadFromExtension('security', ['providers' => ['memory' => ['memory' => null]], 'firewalls' => ['dev' => ['security' => false], 'main' => ['lazy' => true]]]);
        $container->loadFromExtension('security', ['providers' => ['extra' => ['id' => 'x']], 'firewalls' => ['api' => ['stateless' => true]]]);

        FirewallComposer::prepend($container, $this->config());

        /** @var array{providers: array<string, array<string, mixed>>, firewalls: array<string, array<string, mixed>>} $first */
        $first = $container->getExtensionConfig('security')[0];
        self::assertSame([FirewallComposer::PROVIDER, 'memory', 'extra'], array_keys($first['providers']));
        self::assertSame(['id' => FirewallComposer::USER_PROVIDER_SERVICE], $first['providers'][FirewallComposer::PROVIDER]);
        self::assertSame([], $first['providers']['memory']);
        self::assertSame(['dev', 'main', 'api'], array_keys($first['firewalls']));
        self::assertSame([], $first['firewalls']['dev']);
        self::assertSame(FirewallComposer::PROVIDER, $first['firewalls']['main']['provider']);
        self::assertArrayHasKey('oidc_login', $first['firewalls']['main']);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return Config
     */
    private function config(array $overrides = []): array
    {
        $bundle = new PhilIdentityClientBundle();
        $container = new ContainerBuilder();
        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], $container);
        self::assertNotNull($configuration);

        /** @var Config $config */
        $config = new Processor()->processConfiguration($configuration, [array_replace(self::REQUIRED, $overrides)]);

        return $config;
    }
}
