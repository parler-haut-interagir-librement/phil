<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit;

use ErrorException;
use Phil\IdentityProviderBundle\Application\AccountAddresses;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Contract\AccountIdGenerator;
use Phil\IdentityProviderBundle\Application\Contract\AddressCanonicalizer;
use Phil\IdentityProviderBundle\Application\Contract\AuditRecorder;
use Phil\IdentityProviderBundle\Application\Contract\ClaimsProvider;
use Phil\IdentityProviderBundle\Application\Contract\LogoutNotifier;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Contract\SigningKeySource;
use Phil\IdentityProviderBundle\Application\Contract\SmsSender;
use Phil\IdentityProviderBundle\Application\EmailCanonicalizer;
use Phil\IdentityProviderBundle\Application\Identifier\UlidAccountIdGenerator;
use Phil\IdentityProviderBundle\Application\Identifier\UuidV7AccountIdGenerator;
use Phil\IdentityProviderBundle\Application\Modules;
use Phil\IdentityProviderBundle\Application\Oidc\FileSigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\ProviderSettings;
use Phil\IdentityProviderBundle\Application\Oidc\SigningKeys;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Application\Sessions;
use Phil\IdentityProviderBundle\Application\SignedLinks;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Contract\LinkTokenRepository;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Oidc\ClientAuthMethod;
use Phil\IdentityProviderBundle\Domain\Security\LockoutPolicy;
use Phil\IdentityProviderBundle\Domain\WebAuthn\AttestationPolicy;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Audit\NullAuditRecorder;
use Phil\IdentityProviderBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\IdentityProviderBundle\Infrastructure\Console\SealKeyCommand;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\KeyAndAttestationServices;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\LoginLinkPostOnlyPass;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineLinkTokenRepository;
use Phil\IdentityProviderBundle\Infrastructure\Http\AccountController;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryLinkTokenRepository;
use Phil\IdentityProviderBundle\Infrastructure\Messenger\SendLogoutTokenHandler;
use Phil\IdentityProviderBundle\Infrastructure\Notifier\NotifierSmsSender;
use Phil\IdentityProviderBundle\Infrastructure\Secret\KeyManagementSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Security\LoginRateLimiter;
use Phil\IdentityProviderBundle\Infrastructure\Security\RegistrationRateLimiter;
use Phil\IdentityProviderBundle\Infrastructure\Twig\KitComponents;
use Phil\IdentityProviderBundle\PhilIdentityProviderBundle;
use Phil\IdentityProviderBundle\Tests\Unit\Fixture\AttestationCertificates;
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
#[CoversClass(KeyAndAttestationServices::class)]
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
        self::assertSame([false, true], [$arguments[19], $arguments[20]], 'The audience of an assertion is the issuer alone, and a request without PKCE carries a nonce, by default.');
    }

    public function testTheCompatibilityOfAssertionsAndTheNonceAreRead(): void
    {
        $container = $this->load([[
            'store' => 'memory',
            'modules' => ['oidc' => true],
            'secrets' => ['allow_plaintext_secrets' => true],
            'oidc' => ['issuer' => 'https://example.com', 'client_authentication' => ['legacy_assertion_audiences' => true], 'require_nonce_without_pkce' => false],
        ]]);

        $arguments = $container->getDefinition(ProviderSettings::class)->getArguments();
        self::assertSame([true, false], [$arguments[19], $arguments[20]]);
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
        self::assertContains(LoginLinkPostOnlyPass::class, $passes);
    }

    public function testTheSessionBoundsOfZeroAreNoBoundAndOthersAreKept(): void
    {
        $unbound = $this->load([['sessions' => ['touch_interval' => 0, 'idle_timeout' => 0, 'max_age' => 0]]])->getDefinition(Sessions::class)->getArguments();
        self::assertNull($unbound[8]);
        self::assertNull($unbound[9]);

        $bound = $this->load([['sessions' => ['touch_interval' => 0, 'idle_timeout' => 600, 'max_age' => 900]]])->getDefinition(Sessions::class)->getArguments();
        self::assertSame(600, $bound[8]);
        self::assertSame(900, $bound[9]);
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

    public function testTheStoredLinksHaveTheirStoreAndTheirTable(): void
    {
        self::assertFalse($this->load([])->hasDefinition(LinkTokenRepository::class));
        self::assertNull($this->load([])->getDefinition(SignedLinks::class)->getArgument(4));

        $container = $this->load([['links' => ['mode' => 'stored'], 'store' => 'memory']]);
        self::assertSame(InMemoryLinkTokenRepository::class, $container->getDefinition(LinkTokenRepository::class)->getClass());
        self::assertEquals(new Reference(LinkTokenRepository::class), $container->getDefinition(SignedLinks::class)->getArgument(4));
        self::assertSame(DoctrineLinkTokenRepository::class, $this->load([['links' => ['mode' => 'stored']]])->getDefinition(LinkTokenRepository::class)->getClass());

        foreach ([[['links' => ['mode' => 'stored']]], [['links' => ['mode' => 'signed']], ['links' => ['mode' => 'stored']]], [['links' => ['mode' => 'stored']], ['links' => ['verify_lifetime' => 60]]]] as $configs) {
            self::assertContains('PhilIdentityProviderLink', $this->prependedMappings($configs));
        }

        foreach ([[], [['links' => ['mode' => 'signed']]], [['links' => ['mode' => 'stored']], ['links' => ['mode' => 'signed']]], [['links' => 'stored']]] as $configs) {
            self::assertNotContains('PhilIdentityProviderLink', $this->prependedMappings($configs));
        }
    }

    public function testTheIdentifiersAndTheCanonicalAddresses(): void
    {
        $container = $this->load([]);
        self::assertSame(UlidAccountIdGenerator::class, $container->getDefinition(AccountIdGenerator::class)->getClass());
        self::assertSame(EmailCanonicalizer::class, $container->getDefinition(AddressCanonicalizer::class)->getClass());
        self::assertSame([false], $container->getDefinition(AddressCanonicalizer::class)->getArguments());

        $container = $this->load([['accounts' => ['id_generator' => 'uuid_v7', 'strip_subaddress' => true]]]);
        self::assertSame(UuidV7AccountIdGenerator::class, $container->getDefinition(AccountIdGenerator::class)->getClass());
        self::assertSame([true], $container->getDefinition(AddressCanonicalizer::class)->getArguments());

        $container = $this->load([['accounts' => ['id_generator' => 'app.ids', 'canonicalizer' => 'app.canonical']]]);
        self::assertSame('app.ids', (string) $container->getAlias(AccountIdGenerator::class));
        self::assertSame('app.canonical', (string) $container->getAlias(AddressCanonicalizer::class));
        self::assertTrue($container->getDefinition(AccountAddresses::class)->isPublic());
    }

    public function testTheLockoutCanBeTurnedOff(): void
    {
        self::assertSame([5, 60, 3600, true], $this->load([])->getDefinition(LockoutPolicy::class)->getArguments());
        self::assertSame([5, 60, 3600, false], $this->load([['lockout' => false]])->getDefinition(LockoutPolicy::class)->getArguments());
    }

    public function testTheSourcesOfTheSigningKeys(): void
    {
        $oidc = ['modules' => ['oidc' => true], 'oidc' => ['issuer' => 'https://example.com'], 'secrets' => ['allow_plaintext_secrets' => true]];
        $container = $this->load([$oidc]);
        self::assertSame(SigningKeys::class, (string) $container->getAlias(SigningKeySource::class));
        self::assertTrue($container->getAlias(SigningKeySource::class)->isPublic());
        self::assertTrue($container->getDefinition(RotateKeysCommand::class)->getArgument(1));
        self::assertFalse($container->hasDefinition(SealKeyCommand::class));
        self::assertFalse($this->load([$this->merged($oidc, ['oidc' => ['signing_keys' => ['automatic_rotation' => false]]])])->getDefinition(RotateKeysCommand::class)->getArgument(1));

        $container = $this->load([$this->merged($oidc, ['oidc' => ['algorithm' => 'EdDSA', 'signing_keys' => ['source' => 'file', 'files' => ['/keys/current.pem', ['path' => '/keys/previous.pem', 'kid' => 'previous']]]]])]);
        self::assertSame(FileSigningKeys::class, (string) $container->getAlias(SigningKeySource::class));
        $file = $container->getDefinition(FileSigningKeys::class);
        self::assertSame([['path' => '/keys/current.pem', 'kid' => null], ['path' => '/keys/previous.pem', 'kid' => 'previous']], $file->getArgument(0));
        $algorithm = $file->getArgument(1);
        self::assertInstanceOf(Definition::class, $algorithm);
        self::assertSame(['EdDSA'], $algorithm->getArguments());
        self::assertNull($file->getArgument(2));
        self::assertFalse($container->hasDefinition(RotateKeysCommand::class));

        $sealed = $this->load([$this->merged($oidc, ['secrets' => ['kms_key' => 'identity'], 'oidc' => ['signing_keys' => ['source' => 'file', 'sealed' => true, 'files' => ['/keys/current.sealed']]]])]);
        self::assertEquals(new Reference(SecretCipher::class), $sealed->getDefinition(FileSigningKeys::class)->getArgument(2));
        self::assertTrue($sealed->hasDefinition(SealKeyCommand::class));
        $command = $sealed->getDefinition(SealKeyCommand::class)->getArgument(1);
        self::assertInstanceOf(Definition::class, $command);
        self::assertSame(['RS256'], $command->getArguments());

        $service = $this->load([$this->merged($oidc, ['oidc' => ['signing_keys' => ['source' => 'service', 'service' => 'app.hsm']]])]);
        self::assertSame('app.hsm', (string) $service->getAlias(SigningKeySource::class));
        self::assertTrue($service->getAlias(SigningKeySource::class)->isPublic());
        self::assertFalse($service->hasDefinition(RotateKeysCommand::class));

        foreach ([
            [['source' => 'file'], 'needs "files"'],
            [['source' => 'service'], 'needs "service"'],
            [['source' => 'file', 'files' => ['/k'], 'sealed' => true], '"oidc.signing_keys.sealed"'],
            [['sealed' => true], '"oidc.signing_keys.sealed"'],
        ] as [$keys, $message]) {
            try {
                $this->load([$this->merged($oidc, ['oidc' => ['signing_keys' => $keys]])]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalid) {
                self::assertStringContainsString($message, $invalid->getMessage());
            }
        }
    }

    public function testTheAttestationPolicyReadsItsRoots(): void
    {
        $passkeys = ['modules' => ['passkeys' => true], 'passkeys' => ['rp_id' => 'example.com', 'origins' => ['https://example.com']]];
        $root = AttestationCertificates::issue(['commonName' => 'Root']);
        $file = tempnam(sys_get_temp_dir(), 'phil-root-');
        self::assertIsString($file);
        file_put_contents($file, $root['cert']);
        $garbage = tempnam(sys_get_temp_dir(), 'phil-root-');
        self::assertIsString($garbage);
        file_put_contents($garbage, 'not a certificate');

        // A warning of a read fails the test: a directory is refused before it is read.
        set_error_handler(static fn (int $level, string $message): bool => str_starts_with($message, 'file_get_contents') ? throw new ErrorException($message, 0, $level) : true);

        try {
            $container = $this->load([$this->merged($passkeys, ['passkeys' => ['attestation' => ['conveyance' => 'direct', 'trusted_roots' => [$file], 'require_trusted' => true, 'allowed_aaguids' => ['01234567-89ab-cdef-0123-456789abcdef'], 'backup' => 'forbidden']]])]);
            $policy = $container->getDefinition(RelyingParty::class)->getArgument(5);
            self::assertInstanceOf(Definition::class, $policy);
            self::assertSame(AttestationPolicy::class, $policy->getClass());
            self::assertSame(['direct', [$root['cert']], true, ['01234567-89ab-cdef-0123-456789abcdef'], [], 'forbidden'], $policy->getArguments());
            self::assertSame(300_000, $container->getDefinition(RelyingParty::class)->getArgument(4));

            foreach ([
                [['trusted_roots' => ['/no/such/root.pem']], 'is not a readable PEM certificate'],
                [['trusted_roots' => [$garbage]], 'is not a readable PEM certificate'],
                [['trusted_roots' => [sys_get_temp_dir()]], 'is not a readable PEM certificate'],
                [['require_trusted' => true], '"passkeys.attestation": A trusted attestation needs'],
                [['denied_aaguids' => ['nope']], '"passkeys.attestation": "nope" is not an AAGUID'],
            ] as [$attestation, $message]) {
                try {
                    $this->load([$this->merged($passkeys, ['passkeys' => ['attestation' => $attestation]])]);
                    self::fail($message);
                } catch (InvalidConfigurationException $invalid) {
                    self::assertStringContainsString($message, $invalid->getMessage());
                    self::assertTrue(!str_contains($message, '"passkeys.attestation":') || $invalid->getPrevious() instanceof InvalidValue);
                }
            }
        } finally {
            restore_error_handler();
            unlink($file);
            unlink($garbage);
        }
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

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $more
     *
     * @return array<string, mixed>
     */
    private function merged(array $base, array $more): array
    {
        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive($base, $more);

        return $merged;
    }

    /**
     * @param list<array<string, mixed>> $configs in the order the application gives them
     *
     * @return list<string> the names of the Doctrine mappings the bundle prepends
     */
    private function prependedMappings(array $configs): array
    {
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

        return array_map(strval(...), array_keys($doctrine['orm']['mappings']));
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
