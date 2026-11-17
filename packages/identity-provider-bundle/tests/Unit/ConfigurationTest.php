<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit;

use Phil\IdentityProviderBundle\PhilIdentityProviderBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

/**
 * The configuration tree: what it gives when nothing is set, and the limits of each number.
 */
#[CoversClass(PhilIdentityProviderBundle::class)]
final class ConfigurationTest extends TestCase
{
    private const array DEFAULTS = [
        'enabled' => true,
        'secret' => '%kernel.secret%',
        'site_name' => 'PHIL',
        'modules' => [
            'accounts' => true,
            'password' => true,
            'passkeys' => false,
            'totp' => false,
            'recovery_codes' => false,
            'login_link' => false,
            'sms' => false,
            'sessions' => true,
            'step_up' => true,
            'external' => false,
            'oidc' => false,
        ],
        'store' => 'doctrine',
        'entity_manager' => 'default',
        'clock' => 'clock',
        'layout' => '@PhilIdentityProvider/layout.html.twig',
        'kit_subdirectory' => null,
        'registration' => true,
        'require_verified_email' => false,
        'login_route' => null,
        'default_target' => '/',
        'open_routes' => [],
        'mailer' => [
            'from' => null,
        ],
        'password' => [
            'min_score' => 2,
            'breach_check' => false,
            'http_client' => 'http_client',
        ],
        'lockout' => [
            'threshold' => 5,
            'base' => 60,
            'max' => 3600,
        ],
        'login_limiter' => [
            'enabled' => false,
            'max_attempts' => 5,
            'network_max_attempts' => 25,
            'account_max_attempts' => 50,
            'interval' => '15 minutes',
            'cache_pool' => 'cache.rate_limiter',
            'lock_factory' => null,
            'single_server' => false,
        ],
        'links' => [
            'verify_lifetime' => 86400,
            'reset_lifetime' => 3600,
        ],
        'passkeys' => [
            'rp_id' => null,
            'rp_name' => null,
            'origins' => [],
            'require_user_verification' => false,
        ],
        'totp' => [
            'issuer' => null,
            'window' => 1,
        ],
        'sms' => [
            'sender' => null,
            'from' => '',
            'message' => 'Your code: {code}',
            'lifetime' => 600,
            'attempts' => 5,
        ],
        'step_up' => [
            'attribute' => 'PHIL_STEP_UP',
            'lifetime' => 300,
        ],
        'sessions' => [
            'touch_interval' => 300,
        ],
        'external' => [
            'link_verified_email' => false,
            'register' => true,
        ],
        'oidc' => [
            'issuer' => null,
            'algorithm' => 'RS256',
            'code_lifetime' => 60,
            'access_token_lifetime' => 600,
            'id_token_lifetime' => 600,
            'refresh_token_lifetime' => 2592000,
            'key_activation_delay' => 86400,
            'retired_key_retention' => 604800,
            'allow_localhost' => false,
            'lock_factory' => 'lock.factory',
        ],
        'secrets' => [
            'kms_key' => null,
            'allow_plaintext_secrets' => false,
            'encrypter' => 'key_management.encrypter',
            'decrypter' => 'key_management.decrypter',
        ],
        'audit' => 'auto',
    ];

    public function testTheDefaults(): void
    {
        self::assertSame(self::DEFAULTS, $this->process([]));
    }

    /**
     * @param list<string> $path
     */
    #[DataProvider('limits')]
    public function testTheLimitsOfANumber(array $path, int $min, ?int $max): void
    {
        self::assertSame($min, $this->read($this->process($this->at($path, $min)), $path));
        $this->assertRefused($this->at($path, $min - 1));

        if (null !== $max) {
            self::assertSame($max, $this->read($this->process($this->at($path, $max)), $path));
            $this->assertRefused($this->at($path, $max + 1));
        }
    }

    /**
     * @return iterable<string, array{list<string>, int, ?int}>
     */
    public static function limits(): iterable
    {
        yield 'password.min_score' => [['password', 'min_score'], 0, 4];
        yield 'lockout.threshold' => [['lockout', 'threshold'], 1, null];
        yield 'lockout.base' => [['lockout', 'base'], 1, null];
        yield 'lockout.max' => [['lockout', 'max'], 1, null];
        yield 'links.verify_lifetime' => [['links', 'verify_lifetime'], 60, null];
        yield 'links.reset_lifetime' => [['links', 'reset_lifetime'], 60, null];
        yield 'totp.window' => [['totp', 'window'], 0, 10];
        yield 'sms.lifetime' => [['sms', 'lifetime'], 60, null];
        yield 'sms.attempts' => [['sms', 'attempts'], 1, null];
        yield 'step_up.lifetime' => [['step_up', 'lifetime'], 30, null];
        yield 'sessions.touch_interval' => [['sessions', 'touch_interval'], 0, null];
        yield 'oidc.code_lifetime' => [['oidc', 'code_lifetime'], 1, 600];
        yield 'oidc.access_token_lifetime' => [['oidc', 'access_token_lifetime'], 1, null];
        yield 'oidc.id_token_lifetime' => [['oidc', 'id_token_lifetime'], 1, null];
        yield 'oidc.refresh_token_lifetime' => [['oidc', 'refresh_token_lifetime'], 1, null];
        yield 'oidc.key_activation_delay' => [['oidc', 'key_activation_delay'], 0, null];
        yield 'oidc.retired_key_retention' => [['oidc', 'retired_key_retention'], 0, null];
    }

    public function testTheChoices(): void
    {
        self::assertSame('memory', $this->process(['store' => 'memory'])['store']);
        self::assertSame('doctrine', $this->process(['store' => 'doctrine'])['store']);
        $this->assertRefused(['store' => 'redis']);

        foreach (['RS256', 'ES256', 'EdDSA'] as $algorithm) {
            $oidc = $this->process(['oidc' => ['algorithm' => $algorithm]])['oidc'];
            self::assertIsArray($oidc);
            self::assertSame($algorithm, $oidc['algorithm']);
        }

        $this->assertRefused(['oidc' => ['algorithm' => 'HS256']]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<array-key, mixed>
     */
    private function process(array $config): array
    {
        $extension = new PhilIdentityProviderBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertNotNull($configuration);

        return new Processor()->processConfiguration($configuration, [$config]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function assertRefused(array $config): void
    {
        try {
            $this->process($config);
            self::fail('The configuration is accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('phil_identity_provider', $invalidConfigurationException->getMessage());
        }
    }

    /**
     * @param list<string> $path
     *
     * @return array<string, mixed>
     */
    private function at(array $path, int $value): array
    {
        return [$path[0] => [$path[1] => $value]];
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<string>            $path
     */
    private function read(array $config, array $path): mixed
    {
        $section = $config[$path[0]];
        self::assertIsArray($section);

        return $section[$path[1]];
    }
}
