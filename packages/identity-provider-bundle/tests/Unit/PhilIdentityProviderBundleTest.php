<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Contract\AuditRecorder;
use Phil\IdentityProviderBundle\Application\Contract\SecretCipher;
use Phil\IdentityProviderBundle\Application\Contract\SmsSender;
use Phil\IdentityProviderBundle\Application\Modules;
use Phil\IdentityProviderBundle\Application\Oidc\Tokens;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Phil\IdentityProviderBundle\Application\SecondFactors;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Contract\GrantRepository;
use Phil\IdentityProviderBundle\Domain\Contract\PasskeyRepository;
use Phil\IdentityProviderBundle\Domain\WebAuthn\RelyingParty;
use Phil\IdentityProviderBundle\Infrastructure\Audit\NullAuditRecorder;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\IdentityProviderServices;
use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\IdentityProviderBundle\Infrastructure\Doctrine\DoctrineAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Memory\InMemoryAccountRepository;
use Phil\IdentityProviderBundle\Infrastructure\Notifier\NotifierSmsSender;
use Phil\IdentityProviderBundle\Infrastructure\Secret\KeyManagementSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Secret\PlainSecretCipher;
use Phil\IdentityProviderBundle\Infrastructure\Twig\KitComponents;
use Phil\IdentityProviderBundle\PhilIdentityProviderBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

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
        ] as [$config, $message]) {
            try {
                $this->load([$config]);
                self::fail($message);
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
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
