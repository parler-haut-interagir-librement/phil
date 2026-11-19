<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\PrivacyBundle\PhilPrivacyBundle;
use Phil\UserSecretsBundle\Application\Audit\AuditTrail;
use Phil\UserSecretsBundle\Application\Contract\SecretAuditor;
use Phil\UserSecretsBundle\Application\Contract\TokenRefresher;
use Phil\UserSecretsBundle\Application\Contract\TokenRevoker;
use Phil\UserSecretsBundle\Application\Encryption\Envelope;
use Phil\UserSecretsBundle\Application\KeyRotation;
use Phil\UserSecretsBundle\Application\UserSecrets;
use Phil\UserSecretsBundle\Domain\Contract\StoredSecretRepository;
use Phil\UserSecretsBundle\Infrastructure\Audit\LoggerAuditor;
use Phil\UserSecretsBundle\Infrastructure\Console\GenerateKeyCommand;
use Phil\UserSecretsBundle\Infrastructure\Console\RotateKeysCommand;
use Phil\UserSecretsBundle\Infrastructure\DependencyInjection\KmsPass;
use Phil\UserSecretsBundle\Infrastructure\DependencyInjection\RefreshLockPass;
use Phil\UserSecretsBundle\Infrastructure\DependencyInjection\UserSecretsServices;
use Phil\UserSecretsBundle\Infrastructure\Doctrine\DoctrineStoredSecretRepository;
use Phil\UserSecretsBundle\Infrastructure\KeyManagement\KeyManagementCipher;
use Phil\UserSecretsBundle\Infrastructure\Lock\SymfonyRefreshLock;
use Phil\UserSecretsBundle\Infrastructure\Memory\InMemoryStoredSecretRepository;
use Phil\UserSecretsBundle\Infrastructure\Privacy\UserSecretsPrivacy;
use Phil\UserSecretsBundle\PhilUserSecretsBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;
use Symfony\Component\KeyManagement\Local\SodiumKms;
use Symfony\Component\Lock\LockFactory;

#[CoversClass(PhilUserSecretsBundle::class)]
#[CoversClass(UserSecretsServices::class)]
#[CoversClass(KmsPass::class)]
#[CoversClass(RefreshLockPass::class)]
final class PhilUserSecretsBundleTest extends TestCase
{
    public function testTheServices(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_user_secrets', $this->extension()->getAlias());
        self::assertSame(['service_container', KeyManagementCipher::class, Envelope::class, DoctrineStoredSecretRepository::class, AuditTrail::class, UserSecrets::class, KeyRotation::class, GenerateKeyCommand::class, RotateKeysCommand::class, UserSecretsPrivacy::class], array_keys($container->getDefinitions()));
        self::assertSame(DataKeyGeneratorInterface::class, $container->getParameter(UserSecretsServices::KMS_PARAMETER));
        self::assertEquals([new Reference(DataKeyGeneratorInterface::class)], $container->getDefinition(KeyManagementCipher::class)->getArguments());
        self::assertEquals([new Reference(KeyManagementCipher::class), 'k1'], $container->getDefinition(Envelope::class)->getArguments());
        self::assertEquals([new Reference('doctrine.orm.default_entity_manager')], $container->getDefinition(DoctrineStoredSecretRepository::class)->getArguments());
        self::assertSame([DoctrineStoredSecretRepository::class, true], [(string) $container->getAlias(StoredSecretRepository::class), $container->getAlias(StoredSecretRepository::class)->isPublic()]);
        self::assertEquals([new Reference('clock'), new TaggedIteratorArgument(UserSecretsServices::AUDITOR_TAG)], $container->getDefinition(AuditTrail::class)->getArguments());
        self::assertEquals([new Reference(StoredSecretRepository::class), new Reference(Envelope::class), new Reference(AuditTrail::class), new Reference('clock'), new TaggedIteratorArgument(UserSecretsServices::REFRESHER_TAG), 60, new TaggedIteratorArgument(UserSecretsServices::REVOKER_TAG), null, new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(UserSecrets::class)->getArguments());
        self::assertEquals([new Reference(StoredSecretRepository::class), new Reference(Envelope::class), new Reference(AuditTrail::class)], $container->getDefinition(KeyRotation::class)->getArguments());
        self::assertEquals([new Reference(KeyRotation::class)], $container->getDefinition(RotateKeysCommand::class)->getArguments());
        self::assertTrue($container->getDefinition(GenerateKeyCommand::class)->hasTag('console.command'));
        self::assertTrue($container->getDefinition(RotateKeysCommand::class)->hasTag('console.command'));
        self::assertEquals([new Reference(UserSecrets::class)], $container->getDefinition(UserSecretsPrivacy::class)->getArguments());
        self::assertTrue($container->getDefinition(UserSecretsPrivacy::class)->hasTag(PhilPrivacyBundle::EXPORTER_TAG));
        self::assertTrue($container->getDefinition(UserSecretsPrivacy::class)->hasTag(PhilPrivacyBundle::ERASER_TAG));
        self::assertTrue($container->getDefinition(UserSecrets::class)->isPublic());
        self::assertTrue($container->getDefinition(KeyRotation::class)->isPublic());
        self::assertFalse($container->getDefinition(KeyManagementCipher::class)->isPublic());
        self::assertSame([UserSecretsServices::REFRESHER_TAG => [[]]], $container->getAutoconfiguredInstanceof()[TokenRefresher::class]->getTags());
        self::assertSame([UserSecretsServices::AUDITOR_TAG => [[]]], $container->getAutoconfiguredInstanceof()[SecretAuditor::class]->getTags());
        self::assertSame([UserSecretsServices::REVOKER_TAG => [[]]], $container->getAutoconfiguredInstanceof()[TokenRevoker::class]->getTags());
        self::assertSame(['lock.factory', false, 30], [
            $container->getParameter(UserSecretsServices::LOCK_FACTORY_PARAMETER),
            $container->getParameter(UserSecretsServices::LOCK_FACTORY_REQUIRED_PARAMETER),
            $container->getParameter(UserSecretsServices::LOCK_TTL_PARAMETER),
        ]);
    }

    public function testTheRefreshLockPassIsAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilUserSecretsBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof RefreshLockPass));
    }

    public function testFrameworkBundlesLockFactoryLocksRefreshesWhenItIsDefined(): void
    {
        $container = $this->load(['refresh_lock_ttl' => 12]);
        $container->register('lock.factory', LockFactory::class);
        new RefreshLockPass()->process($container);

        self::assertEquals([new Reference('lock.factory'), 12.0], $container->getDefinition(SymfonyRefreshLock::class)->getArguments());
        self::assertEquals(new Reference(SymfonyRefreshLock::class), $container->getDefinition(UserSecrets::class)->getArgument(UserSecretsServices::LOCK_ARGUMENT));
    }

    public function testTheLockTimeToLiveIsAFloatOfAtLeastOneSecond(): void
    {
        $container = $this->load(['refresh_lock_ttl' => 1]);
        $container->register('lock.factory', LockFactory::class);
        new RefreshLockPass()->process($container);

        self::assertSame(1, $container->getParameter(UserSecretsServices::LOCK_TTL_PARAMETER));
        self::assertSame(1.0, $container->getDefinition(SymfonyRefreshLock::class)->getArgument(1));
    }

    public function testAnUnreadableLockTimeToLiveFallsBackToThirtySeconds(): void
    {
        $container = new ContainerBuilder();
        UserSecretsServices::register($container, [
            'current_key' => 'k1', 'kms' => null, 'store' => 'memory', 'entity_manager' => 'default',
            'refresh_leeway' => 60, 'lock_factory' => null, 'refresh_lock_ttl' => null, 'log_events' => false,
        ]);
        self::assertSame(30, $container->getParameter(UserSecretsServices::LOCK_TTL_PARAMETER));

        $container->register('lock.factory', LockFactory::class);
        $container->setParameter(UserSecretsServices::LOCK_TTL_PARAMETER, 'soon');
        new RefreshLockPass()->process($container);

        self::assertSame(30.0, $container->getDefinition(SymfonyRefreshLock::class)->getArgument(1));
    }

    public function testANamedLockFactoryLocksRefreshes(): void
    {
        $container = $this->load(['lock_factory' => 'app.locks']);
        $container->register('app.locks', LockFactory::class);
        new RefreshLockPass()->process($container);

        self::assertEquals([new Reference('app.locks'), 30.0], $container->getDefinition(SymfonyRefreshLock::class)->getArguments());
    }

    public function testWithoutALockFactoryRefreshesRelyOnTheCompareAndSwap(): void
    {
        $undefined = $this->load([]);
        new RefreshLockPass()->process($undefined);

        $disabled = $this->load(['lock_factory' => false]);
        $disabled->register('lock.factory', LockFactory::class);
        new RefreshLockPass()->process($disabled);

        $bare = new ContainerBuilder();
        $bare->setParameter(UserSecretsServices::LOCK_FACTORY_PARAMETER, 'lock.factory');
        new RefreshLockPass()->process($bare);

        self::assertSame([false, false, null, null], [
            $undefined->hasDefinition(SymfonyRefreshLock::class),
            $disabled->hasDefinition(SymfonyRefreshLock::class),
            $undefined->getDefinition(UserSecrets::class)->getArgument(UserSecretsServices::LOCK_ARGUMENT),
            $disabled->getParameter(UserSecretsServices::LOCK_FACTORY_PARAMETER),
        ]);
        self::assertFalse($bare->hasDefinition(SymfonyRefreshLock::class));
    }

    public function testANamedLockFactoryThatIsNotDefinedStopsTheContainer(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"phil_user_secrets.lock_factory" names the service "app.locks", which is not defined: install symfony/lock and configure "framework.lock", or name another lock factory.');

        new RefreshLockPass()->process($this->load(['lock_factory' => 'app.locks']));
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load(['current_key' => 'k2', 'store' => 'memory', 'refresh_leeway' => 0, 'log_events' => true]);

        self::assertEquals([new Reference(KeyManagementCipher::class), 'k2'], $container->getDefinition(Envelope::class)->getArguments());
        self::assertTrue($container->hasDefinition(InMemoryStoredSecretRepository::class));
        self::assertFalse($container->hasDefinition(DoctrineStoredSecretRepository::class));
        self::assertSame([InMemoryStoredSecretRepository::class, true], [(string) $container->getAlias(StoredSecretRepository::class), $container->getAlias(StoredSecretRepository::class)->isPublic()]);
        self::assertSame(0, $container->getDefinition(UserSecrets::class)->getArgument(5));
        self::assertEquals([new Reference('logger')], $container->getDefinition(LoggerAuditor::class)->getArguments());
        self::assertTrue($container->getDefinition(LoggerAuditor::class)->hasTag(UserSecretsServices::AUDITOR_TAG));
        self::assertEquals([new Reference('doctrine.orm.secrets_entity_manager')], $this->load(['entity_manager' => 'secrets'])->getDefinition(DoctrineStoredSecretRepository::class)->getArguments());
        self::assertFalse($this->load([])->hasDefinition(LoggerAuditor::class));
        self::assertTrue($this->load(['store' => 'doctrine'])->hasDefinition(DoctrineStoredSecretRepository::class));
        self::assertSame('arn:aws:kms:ca-central-1:000000000000:alias/secrets', $this->load(['current_key' => 'arn:aws:kms:ca-central-1:000000000000:alias/secrets'])->getDefinition(Envelope::class)->getArgument(1));
    }

    public function testANamedKmsSealsInsteadOfTheDefaultClient(): void
    {
        $container = $this->load(['current_key' => 'master', 'kms' => 'key_management.vault']);

        self::assertSame('key_management.vault', $container->getParameter(UserSecretsServices::KMS_PARAMETER));
        self::assertEquals([new Reference('key_management.vault')], $container->getDefinition(KeyManagementCipher::class)->getArguments());
        self::assertSame('master', $container->getDefinition(Envelope::class)->getArgument(1), 'the master key as the configuration names it');
    }

    public function testTheKmsPassIsAdded(): void
    {
        $container = new ContainerBuilder();
        new PhilUserSecretsBundle()->build($container);

        self::assertSame([KmsPass::class], array_values(array_filter(array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getBeforeOptimizationPasses()), static fn (string $class): bool => KmsPass::class === $class)));
    }

    public function testTheKmsPassLetsADefinedKmsThrough(): void
    {
        $container = $this->load([]);
        $container->register('key_management.local', SodiumKms::class);
        $container->setAlias(DataKeyGeneratorInterface::class, 'key_management.local');
        new KmsPass()->process($container);

        $named = $this->load(['kms' => 'key_management.vault']);
        $named->register('key_management.vault', SodiumKms::class);
        new KmsPass()->process($named);

        $unconfigured = new ContainerBuilder();
        new KmsPass()->process($unconfigured);

        $other = new ContainerBuilder();
        $other->setParameter(UserSecretsServices::KMS_PARAMETER, ['not', 'a', 'name']);
        new KmsPass()->process($other);

        self::assertTrue($named->has('key_management.vault'));
    }

    public function testWithoutAClientOfTheComponentTheContainerStopsAndSaysWhatToChange(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil/user-secrets-bundle seals secrets through Symfony\'s KeyManagement component, but it has no client: register Symfony\Component\KeyManagement\KeyManagementBundle and configure "key_management.clients", such as a local "sodium://" one, or set "phil_user_secrets.kms" to the service of a KMS.');

        new KmsPass()->process($this->load([]));
    }

    public function testANamedKmsThatIsNotDefinedStopsTheContainer(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('"phil_user_secrets.kms" names the service "key_management.vault", which is not defined: name a client of "key_management.clients", such as "key_management.<client>".');

        new KmsPass()->process($this->load(['kms' => 'key_management.vault']));
    }

    public function testABadConfigurationIsRefused(): void
    {
        foreach ([
            [],
            ['current_key' => ''],
            ['current_key' => 'k1', 'kms' => ''],
            ['current_key' => 'k1', 'keys' => ['k1' => 'key']],
            ['current_key' => 'k1', 'store' => 'redis'],
            ['current_key' => 'k1', 'entity_manager' => ''],
            ['current_key' => 'k1', 'refresh_leeway' => -1],
            ['current_key' => 'k1', 'lock_factory' => true],
            ['current_key' => 'k1', 'lock_factory' => ''],
            ['current_key' => 'k1', 'refresh_lock_ttl' => 0],
        ] as $config) {
            try {
                $container = new ContainerBuilder();
                $this->extension()->load([$config], $container);
                self::fail(json_encode($config, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_user_secrets', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheMappingIsPrepended(): void
    {
        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->registerExtension($extension);

        $extension->prepend($container);

        self::assertSame([['orm' => ['mappings' => ['PhilUserSecretsBundle' => [
            'type' => 'attribute',
            'is_bundle' => false,
            'dir' => \dirname(__DIR__, 2).'/src/Domain/Model',
            'prefix' => 'Phil\UserSecretsBundle\Domain\Model',
        ]]]]], $container->getExtensionConfig('doctrine'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertSame([[], []], [$bare->getExtensionConfig('doctrine'), $bare->getExtensionConfig('phil_user_secrets')]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config + ['current_key' => 'k1']], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilUserSecretsBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
