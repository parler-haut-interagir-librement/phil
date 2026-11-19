<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Infrastructure\DependencyInjection;

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
use Phil\UserSecretsBundle\Infrastructure\Doctrine\DoctrineStoredSecretRepository;
use Phil\UserSecretsBundle\Infrastructure\KeyManagement\KeyManagementCipher;
use Phil\UserSecretsBundle\Infrastructure\Memory\InMemoryStoredSecretRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\KeyManagement\DataKeyGeneratorInterface;

/**
 * The bundle's services: the cipher over a client of Symfony's KeyManagement component — the one the configuration
 * names, or the component's default client —, the envelope, the store, the audit trail, `UserSecrets` with the token
 * refreshers and revokers found by autoconfiguration, the key rotation, the optional logger auditor, and the commands
 * when the console is. The refresh lock is added by {@see RefreshLockPass} when a lock factory is defined.
 */
final class UserSecretsServices
{
    public const string REFRESHER_TAG = 'phil_user_secrets.token_refresher';

    public const string AUDITOR_TAG = 'phil_user_secrets.auditor';

    public const string REVOKER_TAG = 'phil_user_secrets.token_revoker';

    /**
     * The parameter that names the service of the lock factory, read by {@see RefreshLockPass}.
     */
    public const string LOCK_FACTORY_PARAMETER = 'phil_user_secrets.lock_factory';

    /**
     * The parameter that tells whether the lock factory was named by the application, read by {@see RefreshLockPass}.
     */
    public const string LOCK_FACTORY_REQUIRED_PARAMETER = 'phil_user_secrets.lock_factory_required';

    public const string LOCK_TTL_PARAMETER = 'phil_user_secrets.refresh_lock_ttl';

    /**
     * The index of the refresh lock among the arguments of `UserSecrets`, set by {@see RefreshLockPass}.
     */
    public const int LOCK_ARGUMENT = 7;

    /**
     * The parameter that names the service of the KMS, read by {@see KmsPass}.
     */
    public const string KMS_PARAMETER = 'phil_user_secrets.kms';

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(TokenRefresher::class)->addTag(self::REFRESHER_TAG);
        $container->registerForAutoconfiguration(SecretAuditor::class)->addTag(self::AUDITOR_TAG);
        $container->registerForAutoconfiguration(TokenRevoker::class)->addTag(self::REVOKER_TAG);
        // null: FrameworkBundle's lock factory when there is one; false: no lock; a service: that one, which must exist.
        $lockFactory = $config['lock_factory'];
        $container->setParameter(self::LOCK_FACTORY_PARAMETER, false === $lockFactory ? null : (\is_string($lockFactory) ? $lockFactory : 'lock.factory'));
        $container->setParameter(self::LOCK_FACTORY_REQUIRED_PARAMETER, \is_string($lockFactory));
        $container->setParameter(self::LOCK_TTL_PARAMETER, \is_int($config['refresh_lock_ttl']) ? $config['refresh_lock_ttl'] : 30);

        $kms = \is_string($config['kms']) ? $config['kms'] : DataKeyGeneratorInterface::class;
        $container->setParameter(self::KMS_PARAMETER, $kms);
        $container->register(KeyManagementCipher::class, KeyManagementCipher::class)->setArguments([new Reference($kms)]);
        $container->register(Envelope::class, Envelope::class)->setArguments([new Reference(KeyManagementCipher::class), $config['current_key']]);

        if ('memory' === $config['store']) {
            $container->register(InMemoryStoredSecretRepository::class, InMemoryStoredSecretRepository::class);
            $container->setAlias(StoredSecretRepository::class, InMemoryStoredSecretRepository::class)->setPublic(true);
        } else {
            $container->register(DoctrineStoredSecretRepository::class, DoctrineStoredSecretRepository::class)
                ->setArguments([new Reference(\sprintf('doctrine.orm.%s_entity_manager', \is_string($config['entity_manager']) ? $config['entity_manager'] : 'default'))]);
            $container->setAlias(StoredSecretRepository::class, DoctrineStoredSecretRepository::class)->setPublic(true);
        }

        $container->register(AuditTrail::class, AuditTrail::class)
            ->setArguments([new Reference('clock'), new TaggedIteratorArgument(self::AUDITOR_TAG)]);
        $container->register(UserSecrets::class, UserSecrets::class)
            ->setArguments([
                new Reference(StoredSecretRepository::class),
                new Reference(Envelope::class),
                new Reference(AuditTrail::class),
                new Reference('clock'),
                new TaggedIteratorArgument(self::REFRESHER_TAG),
                $config['refresh_leeway'],
                new TaggedIteratorArgument(self::REVOKER_TAG),
                null,
                new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ])
            ->setPublic(true);
        $container->register(KeyRotation::class, KeyRotation::class)
            ->setArguments([new Reference(StoredSecretRepository::class), new Reference(Envelope::class), new Reference(AuditTrail::class)])
            ->setPublic(true);

        if (true === $config['log_events']) {
            $container->register(LoggerAuditor::class, LoggerAuditor::class)
                ->setArguments([new Reference('logger')])
                ->addTag(self::AUDITOR_TAG);
        }

        if (class_exists(Command::class)) {
            $container->register(GenerateKeyCommand::class, GenerateKeyCommand::class)->addTag('console.command');
            $container->register(RotateKeysCommand::class, RotateKeysCommand::class)
                ->setArguments([new Reference(KeyRotation::class)])
                ->addTag('console.command');
        }
    }
}
