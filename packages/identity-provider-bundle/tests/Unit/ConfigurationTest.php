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
        'public_origin' => null,
        'accounts' => [
            'id_generator' => 'ulid',
            'strip_subaddress' => false,
            'canonicalizer' => null,
        ],
        'registration' => [
            'enabled' => true,
            'rate_limit' => [
                'enabled' => true,
                'per_ip' => ['limit' => 10, 'interval' => '1 hour'],
                'per_address' => ['limit' => 5, 'interval' => '1 hour'],
                'resend_delay' => '5 minutes',
            ],
        ],
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
            'semaphore' => ['enabled' => true, 'slots' => 4, 'wait' => 3.0, 'lock_factory' => null],
        ],
        'lockout' => [
            'enabled' => true,
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
            'mode' => 'signed',
        ],
        'passkeys' => [
            'rp_id' => null,
            'rp_name' => null,
            'origins' => [],
            'require_user_verification' => false,
            'attestation' => [
                'conveyance' => 'none',
                'trusted_roots' => [],
                'require_trusted' => false,
                'allowed_aaguids' => [],
                'denied_aaguids' => [],
                'backup' => 'allowed',
            ],
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
            'idle_timeout' => 7200,
            'max_age' => 43200,
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
            'signing_keys' => [
                'source' => 'database',
                'automatic_rotation' => true,
                'files' => [],
                'sealed' => false,
                'service' => null,
            ],
            'extra_scopes' => [],
            'client_authentication' => [
                'methods' => ['client_secret_basic', 'client_secret_post', 'private_key_jwt', 'none'],
                'clock_skew' => 60,
                'assertion_max_lifetime' => 300,
                'legacy_assertion_audiences' => false,
            ],
            'require_nonce_without_pkce' => true,
            'refresh_token_bound_to_session' => true,
            'session_max_age' => null,
            'id_token' => [
                'acr' => null,
                'idp' => null,
                'session_start' => false,
            ],
            'subject' => [
                'type' => 'account',
                'salt' => null,
            ],
            'backchannel_logout' => [
                'enabled' => false,
                'bus' => 'messenger.default_bus',
                'http_client' => 'http_client',
                'timeout' => 5,
            ],
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
        yield 'password.semaphore.slots' => [['password', 'semaphore', 'slots'], 1, 256];
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
        yield 'oidc.client_authentication.clock_skew' => [['oidc', 'client_authentication', 'clock_skew'], 0, 300];
        yield 'oidc.client_authentication.assertion_max_lifetime' => [['oidc', 'client_authentication', 'assertion_max_lifetime'], 1, 3600];
        yield 'oidc.session_max_age' => [['oidc', 'session_max_age'], 1, null];
        yield 'oidc.backchannel_logout.timeout' => [['oidc', 'backchannel_logout', 'timeout'], 1, 30];
    }

    public function testTheExtraScopesAreTokensOfPrintableCharacters(): void
    {
        foreach (['read', 'a', str_repeat('a', 64), '!', '~', 'api:read'] as $scope) {
            $oidc = $this->process(['oidc' => ['extra_scopes' => [$scope]]])['oidc'];
            self::assertIsArray($oidc);
            self::assertSame([$scope], $oidc['extra_scopes']);
        }

        foreach (['', str_repeat('a', 65), 'two words', "read\n", "\nread", 'say"', 'back\\slash', 'openid', 'email', 'café'] as $scope) {
            $this->assertRefused(['oidc' => ['extra_scopes' => [$scope]]]);
        }

        $this->assertRefused(['oidc' => ['extra_scopes' => [1]]]);
    }

    public function testASessionIdlesLongerThanItIsSeenAndBothBoundsMayBeTurnedOff(): void
    {
        $sessions = $this->process(['sessions' => ['touch_interval' => 0, 'idle_timeout' => 60]])['sessions'];
        self::assertIsArray($sessions);
        self::assertSame(60, $sessions['idle_timeout']);
        $this->assertRefused(['sessions' => ['touch_interval' => 0, 'idle_timeout' => 59]]);

        $sessions = $this->process(['sessions' => ['touch_interval' => 300, 'idle_timeout' => 301]])['sessions'];
        self::assertIsArray($sessions);
        self::assertSame(301, $sessions['idle_timeout']);
        $this->assertRefused(['sessions' => ['touch_interval' => 300, 'idle_timeout' => 300]]);

        $sessions = $this->process(['sessions' => ['touch_interval' => 9000, 'idle_timeout' => 0, 'max_age' => 0]])['sessions'];
        self::assertIsArray($sessions);
        self::assertSame(0, $sessions['idle_timeout']);
        self::assertSame(0, $sessions['max_age']);
        $this->assertRefused(['sessions' => ['idle_timeout' => -1]], 'greater than or equal to 0');

        $sessions = $this->process(['sessions' => ['max_age' => 60]])['sessions'];
        self::assertIsArray($sessions);
        self::assertSame(60, $sessions['max_age']);
        $this->assertRefused(['sessions' => ['max_age' => 59]]);
        $this->assertRefused(['sessions' => ['max_age' => 1]]);
        $this->assertRefused(['sessions' => ['max_age' => -1]], 'greater than or equal to 0');
    }

    public function testTheWaitForASlotIsFromZeroToThirtySeconds(): void
    {
        foreach ([0.0, 0.5, 30.0] as $wait) {
            $password = $this->process(['password' => ['semaphore' => ['wait' => $wait]]])['password'];
            self::assertIsArray($password);
            self::assertIsArray($password['semaphore']);
            self::assertSame($wait, $password['semaphore']['wait']);
        }

        $this->assertRefused(['password' => ['semaphore' => ['wait' => -0.1]]]);
        $this->assertRefused(['password' => ['semaphore' => ['wait' => 30.1]]]);
    }

    public function testARegistrationCountMayBeOne(): void
    {
        $registration = $this->process(['registration' => ['rate_limit' => ['per_ip' => ['limit' => 1], 'per_address' => ['limit' => 1]]]])['registration'];

        self::assertIsArray($registration);
        self::assertIsArray($registration['rate_limit']);
        self::assertIsArray($registration['rate_limit']['per_ip']);
        self::assertIsArray($registration['rate_limit']['per_address']);
        self::assertSame(1, $registration['rate_limit']['per_ip']['limit']);
        self::assertSame(1, $registration['rate_limit']['per_address']['limit']);
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

        foreach (['signed', 'stored'] as $mode) {
            $links = $this->process(['links' => ['mode' => $mode]])['links'];
            self::assertIsArray($links);
            self::assertSame($mode, $links['mode']);
        }

        $this->assertRefused(['links' => ['mode' => 'cookie']]);

        foreach (['database', 'file', 'service'] as $source) {
            $oidc = $this->process(['oidc' => ['signing_keys' => ['source' => $source]]])['oidc'];
            self::assertIsArray($oidc);
            self::assertIsArray($oidc['signing_keys']);
            self::assertSame($source, $oidc['signing_keys']['source']);
        }

        $this->assertRefused(['oidc' => ['signing_keys' => ['source' => 'vault']]]);
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
    private function assertRefused(array $config, string $reason = ''): void
    {
        try {
            $this->process($config);
            self::fail('The configuration is accepted.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString('phil_identity_provider', $invalidConfigurationException->getMessage());
            self::assertStringContainsString($reason, $invalidConfigurationException->getMessage());
        }
    }

    /**
     * @param list<string> $path
     *
     * @return array<string, mixed>
     */
    private function at(array $path, int $value): array
    {
        [$key, $rest] = [$path[0], \array_slice($path, 1)];

        return [$key => [] === $rest ? $value : $this->at($rest, $value)];
    }

    /**
     * @param array<array-key, mixed> $config
     * @param list<string>            $path
     */
    private function read(array $config, array $path): mixed
    {
        $value = $config;

        foreach ($path as $key) {
            self::assertIsArray($value);
            $value = $value[$key];
        }

        return $value;
    }
}
