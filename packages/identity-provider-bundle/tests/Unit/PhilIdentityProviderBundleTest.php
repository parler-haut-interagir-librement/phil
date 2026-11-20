<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Contract\AuditRecorder;
use Phil\IdentityProviderBundle\Application\Contract\ClaimsProvider;
use Phil\IdentityProviderBundle\Application\Contract\LogoutNotifier;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Contract\SmsSender;
use Phil\IdentityProviderBundle\Application\Modules;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Audit\NullAuditRecorder;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Messenger\SendLogoutTokenHandler;
use Phil\IdentityProviderBundle\Infrastructure\Notifier\NotifierSmsSender;
use Phil\IdentityProviderBundle\Infrastructure\Secret\KeyManagementSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Security\LoginRateLimiter;
use Phil\IdentityProviderBundle\Infrastructure\Security\RegistrationRateLimiter;
use Phil\IdentityProviderBundle\Infrastructure\Twig\KitComponents;
use Phil\IdentityProviderBundle\PhilIdentityProviderBundle;
use Phil\RateLimit\RateLimitGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

#[CoversClass(PhilIdentityProviderBundle::class)]
#[CoversClass(IdentityProviderServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(Modules::class)]
final class PhilIdentityProviderBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_identity_provider', $this->extension()->getAlias());
    }

    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->getParameter('phil_identity_provider.enabled'));
        self::assertSame(DoctrineAccountRepository::class, $container->getDefinition(AccountRepository::class)->getClass());
        self::assertSame(PlainSecretCipher::class, $container->getDefinition(SecretCipher::class)->getClass());
        self::assertSame(NullAuditRecorder::class, $container->getDefinition(AuditRecorder::class)->getClass());
        self::assertSame([['accounts', 'password', 'sessions', 'step_up']], $container->getDefinition(Modules::class)->getArguments());
        self::assertFalse($container->hasDefinition(PasskeyRepository::class));
        self::assertFalse($container->hasDefinition(Passkeys::class));
        self::assertFalse($container->hasDefinition(Tokens::class));
        self::assertTrue($container->hasDefinition(IdentityProviderServices::USER_PROVIDER));
        self::assertTrue($container->hasDefinition(IdentityProviderServices::RE_AUTHENTICATION_ENTRY_POINT));
    }

    public function testEveryModule(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'modules' => ['passkeys' => true, 'totp' => true, 'recovery_codes' => true, 'login_link' => true, 'sms' => true, 'external' => true, 'oidc' => true],
            'passkeys' => ['rp_id' => 'example.com', 'origins' => ['https://example.com']],
            'oidc' => ['issuer' => 'https://example.com'],
            'secrets' => ['kms_key' => 'identity'],
            'audit' => 'app.audit',
        ]]);

        self::assertSame(InMemoryAccountRepository::class, $container->getDefinition(AccountRepository::class)->getClass());
        self::assertTrue($container->getDefinition(GrantRepository::class)->hasTag('kernel.reset'));
        self::assertTrue($container->hasDefinition(Passkeys::class));
        self::assertTrue($container->hasDefinition(SecondFactors::class));
        self::assertTrue($container->hasDefinition(Tokens::class));
        self::assertSame(NotifierSmsSender::class, $container->getDefinition(SmsSender::class)->getClass());
        self::assertSame(KeyManagementSecretCipher::class, $container->getDefinition(SecretCipher::class)->getClass());
        self::assertSame('app.audit', (string) $container->getAlias(AuditRecorder::class));
        $modules = $container->getDefinition(Modules::class)->getArgument(0);
        self::assertIsArray($modules);
        self::assertCount(11, $modules);
    }

    public function testTheProviderSettingsReadEachScopeAndMethodOnceAsAList(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'modules' => ['oidc' => true],
            'secrets' => ['allow_plaintext_secrets' => true],
            'oidc' => ['issuer' => 'https://example.com', 'extra_scopes' => ['a', 'a', 'b'], 'client_authentication' => ['methods' => ['none', 'none', 'private_key_jwt']]],
        ]]);

        $arguments = $container->getDefinition(ProviderSettings::class)->getArguments();
        self::assertSame([...ProviderSettings::SCOPES, 'a', 'b'], $arguments[6]);
        self::assertSame([ClientAuthMethod::None, ClientAuthMethod::PrivateKeyJwt], $arguments[9]);
    }

    public function testTheBackChannelLogoutNeedsItsServicesOnlyWhenTurnedOn(): void
    {
        $base = ['store' => 'memory', 'modules' => ['oidc' => true], 'secrets' => ['allow_plaintext_secrets' => true], 'oidc' => ['issuer' => 'https://example.com']];

        self::assertFalse($this->load([$base])->hasDefinition(LogoutNotifier::class));

        $base['oidc']['backchannel_logout'] = ['enabled' => true, 'bus' => 'app.bus', 'http_client' => 'app.http', 'timeout' => 7];
        $container = $this->load([$base]);

        $bus = $container->getDefinition(LogoutNotifier::class)->getArgument(0);
        $handler = $container->getDefinition(SendLogoutTokenHandler::class)->getArguments();
        self::assertInstanceOf(Reference::class, $bus);
        self::assertInstanceOf(Reference::class, $handler[2]);
        self::assertSame(['app.bus', 'app.http', 7.0], [(string) $bus, (string) $handler[2], $handler[3]]);
    }

    public function testTheClaimsProvidersAreTaggedByAutoconfiguration(): void
    {
        $container = $this->load([['store' => 'memory', 'modules' => ['oidc' => true], 'secrets' => ['allow_plaintext_secrets' => true], 'oidc' => ['issuer' => 'https://example.com']]]);

        self::assertArrayHasKey(ClaimsProvider::class, $container->getAutoconfiguredInstanceof());
        self::assertTrue($container->getAutoconfiguredInstanceof()[ClaimsProvider::class]->hasTag(IdentityProviderServices::CLAIMS_PROVIDER_TAG));
    }

    public function testTheRelyingPartyIsGivenTextsAndTheNameOfItsOwn(): void
    {
        $container = $this->load([['modules' => ['passkeys' => true], 'passkeys' => ['rp_id' => 12_345, 'rp_name' => 'Passkeys', 'origins' => ['https://example.com']], 'site_name' => 'Site']]);
        self::assertSame(['12345', 'Passkeys'], \array_slice($container->getDefinition(RelyingParty::class)->getArguments(), 0, 2));

        $container = $this->load([['modules' => ['passkeys' => true], 'passkeys' => ['rp_id' => 'example.com', 'origins' => ['https://example.com']], 'site_name' => 'Site']]);
        self::assertSame(['example.com', 'Site'], \array_slice($container->getDefinition(RelyingParty::class)->getArguments(), 0, 2));
    }

    public function testTheBundleCanBeTurnedOff(): void
    {
        $container = $this->load([['enabled' => false]]);

        self::assertFalse($container->getParameter('phil_identity_provider.enabled'));
        self::assertFalse($container->hasDefinition(AccountRepository::class));
    }

    public function testModulesThatNeedMore(): void
    {
        foreach ([
            [['modules' => ['accounts' => false]], 'cannot be turned off'],
            [['modules' => ['passkeys' => true]], 'passkeys.rp_id'],
            [['modules' => ['passkeys' => true], 'passkeys' => ['rp_id' => 'example.com']], 'passkeys.origins'],
            [['modules' => ['passkeys' => true], 'passkeys' => ['origins' => ['https://example.com']]], 'passkeys.rp_id'],
            [['modules' => ['oidc' => true]], 'oidc.issuer'],
            [['modules' => ['recovery_codes' => true]], 'turn on "totp"'],
            [['modules' => ['totp' => true]], 'secrets.allow_plaintext_secrets'],
            [['modules' => ['oidc' => true], 'oidc' => ['issuer' => 'https://example.com']], 'secrets.kms_key'],
        ] as [$config, $message]) {
            try {
                $this->load([$config]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testSecretsInClearOnlyWhenTheConfigurationSaysSo(): void
    {
        $container = $this->load([['modules' => ['totp' => true], 'secrets' => ['allow_plaintext_secrets' => true]]]);
        self::assertSame(PlainSecretCipher::class, $container->getDefinition(SecretCipher::class)->getClass());

        $container = $this->load([['modules' => ['totp' => true], 'secrets' => ['kms_key' => 'identity', 'allow_plaintext_secrets' => true]]]);
        self::assertSame(KeyManagementSecretCipher::class, $container->getDefinition(SecretCipher::class)->getClass());

        IdentityProviderServices::assertSecrets([Modules::ACCOUNTS], ['kms_key' => null, 'allow_plaintext_secrets' => false], false);
        IdentityProviderServices::assertSecrets([Modules::TOTP], ['kms_key' => 'identity', 'allow_plaintext_secrets' => false], true);
        IdentityProviderServices::assertSecrets([Modules::OIDC], ['kms_key' => null, 'allow_plaintext_secrets' => true], false);

        $this->expectExceptionObject(new InvalidConfigurationException('"secrets.kms_key" needs symfony/key-management: install it, the secrets are never kept in clear in its place.'));
        IdentityProviderServices::assertSecrets([Modules::TOTP], ['kms_key' => 'identity', 'allow_plaintext_secrets' => true], false);
    }

    public function testTheSignInLimiter(): void
    {
        self::assertFalse($this->load([])->hasDefinition(IdentityProviderServices::LOGIN_LIMITER));

        $container = $this->load([['login_limiter' => ['enabled' => true, 'account_max_attempts' => 40, 'interval' => '10 minutes', 'cache_pool' => 'cache.redis', 'lock_factory' => 'lock.redis.factory']]]);
        $definition = $container->getDefinition(IdentityProviderServices::LOGIN_LIMITER);
        self::assertSame(LoginRateLimiter::class, $definition->getClass());
        self::assertSame(IdentityProviderServices::LOGIN_LIMITER, (string) $container->getAlias(LoginRateLimiter::class));
        $limits = [];

        foreach (\array_slice($definition->getArguments(), 0, 3) as $factory) {
            self::assertInstanceOf(Definition::class, $factory);
            self::assertSame(RateLimiterFactory::class, $factory->getClass());
            [$options, $storage, $lock] = $factory->getArguments();
            self::assertIsArray($options);
            self::assertIsString($options['id']);
            self::assertInstanceOf(Definition::class, $storage);
            self::assertSame(CacheStorage::class, $storage->getClass());
            self::assertEquals(new Reference('cache.redis'), $storage->getArgument(0));
            self::assertEquals(new Reference('lock.redis.factory'), $lock);
            $limits[$options['id']] = [$options['policy'] ?? null, $options['limit'] ?? null, $options['interval'] ?? null];
        }

        self::assertSame([
            'phil_identity_provider.login.network' => ['sliding_window', 25, '10 minutes'],
            'phil_identity_provider.login.local' => ['sliding_window', 5, '10 minutes'],
            'phil_identity_provider.login.account' => ['sliding_window', 40, '10 minutes'],
        ], $limits);
        self::assertIsString($definition->getArgument(3));

        $container = $this->load([['login_limiter' => true]]);
        $factory = $container->getDefinition(IdentityProviderServices::LOGIN_LIMITER)->getArgument(0);
        self::assertInstanceOf(Definition::class, $factory);
        self::assertNull($factory->getArgument(2));

        $this->expectExceptionObject(new InvalidConfigurationException('"login_limiter" needs symfony/rate-limiter: install it, or turn the limiter off.'));
        IdentityProviderServices::loginLimiter(new ContainerBuilder(), ['enabled' => true, 'max_attempts' => 5, 'network_max_attempts' => 25, 'account_max_attempts' => 50, 'interval' => '15 minutes', 'cache_pool' => 'cache.rate_limiter', 'lock_factory' => null, 'single_server' => false], 'secret', false);
    }

    /**
     * The pool of the sign-in limiter is checked as every PHIL rate limit is: in production, a pool local to each server
     * would let a client spend the limits once per server, and the container build refuses it unless the application
     * runs on one.
     */
    public function testASignInLimiterPoolLocalToEachServerIsRefusedInProduction(): void
    {
        $build = static function (array $config, string $environment): void {
            $container = new ContainerBuilder();
            $container->setParameter('kernel.environment', $environment);
            $container->setDefinition('cache.local', new Definition(FilesystemAdapter::class));
            $container->setDefinition('cache.shared', new Definition(RedisAdapter::class));

            $bundle = new PhilIdentityProviderBundle();
            $extension = $bundle->getContainerExtension();
            self::assertNotNull($extension);
            $extension->load([$config], $container);
            $bundle->build($container);

            foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
                if ($pass instanceof RateLimitGuard) {
                    $pass->process($container);
                }
            }
        };

        $build(['login_limiter' => ['enabled' => true, 'cache_pool' => 'cache.local']], 'test');
        $build(['login_limiter' => ['enabled' => true, 'cache_pool' => 'cache.shared']], 'prod');
        $build(['login_limiter' => ['enabled' => true, 'cache_pool' => 'cache.local', 'single_server' => true]], 'prod');
        $build(['login_limiter' => ['enabled' => false, 'cache_pool' => 'cache.local']], 'prod');

        $this->expectExceptionObject(new InvalidConfigurationException('phil_identity_provider.login_limiter.cache_pool: the store "cache.local" is local to each server, so a client could spend the limit once per server. Name a cache pool on a shared store — Redis, Memcached, a database —, set "single_server" to true on a single server, or set "enabled" to false.'));
        $build(['login_limiter' => ['enabled' => true, 'cache_pool' => 'cache.local']], 'prod');
    }

    public function testTheBundleAddsItsCompilerPasses(): void
    {
        $container = new ContainerBuilder();
        new PhilIdentityProviderBundle()->build($container);
        $passes = array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses());

        self::assertContains(KitSubdirectoryPass::class, $passes);
        self::assertContains(RateLimitGuard::class, $passes);
    }

    public function testTheLimitsOfTheSignInLimiterAreOneOrMore(): void
    {
        foreach (['max_attempts', 'network_max_attempts', 'account_max_attempts'] as $option) {
            $limiter = $this->load([['login_limiter' => ['enabled' => true, $option => 1]]])->getDefinition(IdentityProviderServices::LOGIN_LIMITER);
            self::assertSame(LoginRateLimiter::class, $limiter->getClass());

            try {
                $this->load([['login_limiter' => ['enabled' => true, $option => 0]]]);
                self::fail(\sprintf('A limit of 0 was accepted for "%s".', $option));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($option, $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testThePrependedConfiguration(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension($this->fakeExtension('doctrine'));
        $container->registerExtension($this->fakeExtension('security'));
        $container->registerExtension($this->fakeExtension('asset_mapper'));
        $container->prependExtensionConfig('phil_identity_provider', ['modules' => ['passkeys' => true, 'sessions' => false]]);

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        $doctrine = $container->getExtensionConfig('doctrine')[0] ?? [];
        self::assertIsArray($doctrine);
        $orm = $doctrine['orm'] ?? null;
        self::assertIsArray($orm);
        $mappings = $orm['mappings'] ?? null;
        self::assertIsArray($mappings);
        self::assertSame(['PhilIdentityProviderAccount', 'PhilIdentityProviderPasskey'], array_keys($mappings));
        self::assertIsArray($mappings['PhilIdentityProviderPasskey']);
        self::assertSame([
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model/Passkey',
            'prefix' => 'Phil\IdentityProviderBundle\Domain\Model\Passkey',
        ], $mappings['PhilIdentityProviderPasskey']);
        self::assertSame([['password_hashers' => [AccountUser::class => ['algorithm' => 'sodium']]]], $container->getExtensionConfig('security'));
        self::assertSame([['paths' => [PhilIdentityProviderBundle::CONTROLLERS_DIR => '@phil/identity-provider-bundle']]], $container->getExtensionConfig('asset_mapper'));
    }

    public function testThePrependedMappingsOfTheDefaultModules(): void
    {
        foreach ([[], [['site_name' => 'Example']], [['modules' => ['totp' => true]], ['modules' => ['totp' => false, 'external' => true]], []]] as $configs) {
            $container = new ContainerBuilder();
            $container->registerExtension($this->fakeExtension('doctrine'));

            foreach (array_reverse($configs) as $config) {
                $container->prependExtensionConfig('phil_identity_provider', $config);
            }

            $extension = $this->extension();
            self::assertInstanceOf(PrependExtensionInterface::class, $extension);
            $extension->prepend($container);
            $doctrine = $container->getExtensionConfig('doctrine')[0] ?? [];
            self::assertIsArray($doctrine);
            self::assertIsArray($doctrine['orm'] ?? null);
            self::assertIsArray($doctrine['orm']['mappings'] ?? null);
            $expected = [] === $configs || 'site_name' === array_key_first($configs[0])
                ? ['PhilIdentityProviderAccount', 'PhilIdentityProviderSession']
                : ['PhilIdentityProviderAccount', 'PhilIdentityProviderSession', 'PhilIdentityProviderExternal'];
            self::assertSame($expected, array_keys($doctrine['orm']['mappings']));
        }

        $container = new ContainerBuilder();
        $container->registerExtension($this->fakeExtension('doctrine'));
        $container->registerExtension($this->fakeExtension('security'));
        $container->registerExtension($this->fakeExtension('asset_mapper'));

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);
        self::assertCount(1, $container->getExtensionConfig('security'));
        self::assertCount(1, $container->getExtensionConfig('asset_mapper'));
    }

    public function testTheKitSubdirectory(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame('ui', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container = $this->load([['kit_subdirectory' => 'kit']]);
        new KitSubdirectoryPass()->process($container);
        self::assertSame('kit', $container->getDefinition(KitComponents::class)->getArgument(0));

        $container = $this->load([]);
        new KitSubdirectoryPass()->process($container);
        self::assertSame('', $container->getDefinition(KitComponents::class)->getArgument(0));
        new KitSubdirectoryPass()->process(new ContainerBuilder());
    }

    public function testTheRegistrationLimitsAreOnByDefault(): void
    {
        $container = $this->load([]);
        $arguments = $container->getDefinition(IdentityProviderServices::REGISTRATION_LIMITER)->getArguments();
        self::assertSame(RegistrationRateLimiter::class, $container->getDefinition(IdentityProviderServices::REGISTRATION_LIMITER)->getClass());
        self::assertCount(4, $arguments);
        $configs = array_map(static fn (mixed $factory): mixed => $factory instanceof Definition ? $factory->getArgument(0) : null, \array_slice($arguments, 0, 3));
        self::assertSame([
            ['id' => 'phil_identity_provider.registration.ip', 'policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 hour'],
            ['id' => 'phil_identity_provider.registration.address', 'policy' => 'sliding_window', 'limit' => 5, 'interval' => '1 hour'],
            ['id' => 'phil_identity_provider.registration.resend', 'policy' => 'token_bucket', 'limit' => 1, 'rate' => ['interval' => '5 minutes', 'amount' => 1]],
        ], $configs);
        self::assertSame('%kernel.secret%', $arguments[3]);
        self::assertSame(IdentityProviderServices::REGISTRATION_LIMITER, (string) $container->getAlias(RegistrationRateLimiter::class));
        self::assertTrue($container->getDefinition(AccountController::class)->getArgument(4));
        self::assertNull($container->getParameter(IdentityProviderServices::PUBLIC_ORIGIN));
    }

    public function testTheRegistrationLimitsTurnOffExplicitly(): void
    {
        self::assertFalse($this->load([['registration' => ['rate_limit' => false]]])->hasDefinition(IdentityProviderServices::REGISTRATION_LIMITER));

        $closed = $this->load([['registration' => false]]);
        self::assertFalse($closed->hasDefinition(IdentityProviderServices::REGISTRATION_LIMITER));
        self::assertFalse($closed->getDefinition(AccountController::class)->getArgument(4));
        self::assertTrue($this->load([['registration' => true]])->hasDefinition(IdentityProviderServices::REGISTRATION_LIMITER));
    }

    public function testThePublicOriginIsAParameter(): void
    {
        self::assertSame('https://example.com', $this->load([['public_origin' => 'https://example.com']])->getParameter(IdentityProviderServices::PUBLIC_ORIGIN));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function badDurations(): iterable
    {
        yield 'per ip' => [['per_ip' => ['interval' => 'soon']], '"registration.rate_limit.per_ip.interval" is a duration, such as "15 minutes", not "soon".'];
        yield 'per address' => [['per_address' => ['interval' => '-1 hour']], '"registration.rate_limit.per_address.interval" is a duration, such as "15 minutes", not "-1 hour".'];
        yield 'a date' => [['per_ip' => ['interval' => 'tomorrow']], '"registration.rate_limit.per_ip.interval" is a duration, such as "15 minutes", not "tomorrow".'];
        yield 'resend delay' => [['resend_delay' => '0 seconds'], '"registration.rate_limit.resend_delay" is a duration, such as "15 minutes", not "0 seconds".'];
    }

    /**
     * @param array<string, mixed> $limits
     */
    #[DataProvider('badDurations')]
    public function testARegistrationLimitNeedsADuration(array $limits, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);
        $this->load([['registration' => ['rate_limit' => $limits]]]);
    }

    public function testTheRegistrationLimiterIsRegisteredByTheServicesAlone(): void
    {
        $container = new ContainerBuilder();
        $limits = ['enabled' => true, 'per_ip' => ['limit' => 3, 'interval' => '1 hour'], 'per_address' => ['limit' => 2, 'interval' => '1 day'], 'resend_delay' => '5 minutes'];

        IdentityProviderServices::registrationLimiter($container, ['enabled' => false, 'rate_limit' => $limits], 'secret');
        self::assertFalse($container->hasDefinition(IdentityProviderServices::REGISTRATION_LIMITER));

        IdentityProviderServices::registrationLimiter($container, ['enabled' => true, 'rate_limit' => $limits], 'secret');
        self::assertTrue($container->hasDefinition(IdentityProviderServices::REGISTRATION_LIMITER));
    }

    public function testARegistrationLimitIsOneOrMore(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->load([['registration' => ['rate_limit' => ['per_ip' => ['limit' => 0]]]]]);
    }

    public function testTheModulesObject(): void
    {
        $modules = new Modules(['oidc', 'accounts', 'unknown']);

        self::assertTrue($modules->has('oidc'));
        self::assertFalse($modules->has('sms'));
        self::assertSame(['accounts', 'oidc'], $modules->enabled());
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilIdentityProviderBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function fakeExtension(string $alias): ExtensionInterface
    {
        return new readonly class($alias) implements ExtensionInterface {
            public function __construct(private string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getNamespace(): string
            {
                return '';
            }

            public function getXsdValidationBasePath(): false
            {
                return false;
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
