<?php

declare(strict_types=1);

namespace Phil\BackupBundle;

use Override;
use Phil\Backup\Backups;
use Phil\Backup\Database\DatabaseClient;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Encryption\BackupKey;
use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Media\MediaArchive;
use Phil\Backup\Restore\RestoreGuard;
use Phil\Backup\Rotation\RotationPolicy;
use Phil\Backup\Storage\BackupDirectory;
use Phil\BackupBundle\Infrastructure\Console\CreateBackupCommand;
use Phil\BackupBundle\Infrastructure\Console\RestoreBackupCommand;
use Phil\BackupBundle\Infrastructure\Console\VerifyBackupCommand;
use Phil\BackupBundle\Infrastructure\Doctrine\DatabaseClients;
use Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Backups of the application's database and media files, from its configuration:
 *
 * ```yaml
 * phil_backup:
 *     key: '%env(PHIL_BACKUP_KEY)%'
 *     media: { uploads: '%kernel.project_dir%/public/uploads' }
 *     rotation: { last: 3, daily: 7, weekly: 4, monthly: 12 }
 * ```
 *
 * A key is required unless `allow_unencrypted: true` says the backups may be written in clear. With a key, a backup
 * in clear is refused when restored or tested, unless `allow_unencrypted: true` accepts those written before the key.
 *
 * It registers `Backups` over the client of the Doctrine DBAL connection, the backup directory, the cipher, the media
 * archive and the rotation, the `RestoreGuard`, and the commands `phil:backup:create`, `phil:backup:restore` and
 * `phil:backup:verify`.
 */
final class PhilBackupBundle extends AbstractBundle
{
    public const string DATABASE_CLIENT = 'phil_backup.database_client';

    #[Override]
    protected string $extensionAlias = 'phil_backup';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('connection')
                    ->info('Service id of the Doctrine DBAL connection that is backed up and restored.')
                    ->defaultValue('database_connection')
                    ->cannotBeEmpty()
                ->end()
                ->scalarNode('directory')
                    ->info('Where backups are written and found.')
                    ->defaultValue('%kernel.project_dir%/var/backup')
                    ->cannotBeEmpty()
                ->end()
                ->arrayNode('binaries')
                    ->info('Paths of client tools that are not on the PATH, by tool name, such as { pg_dump: /usr/lib/postgresql/17/bin/pg_dump }.')
                    ->useAttributeAsKey('tool')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->end()
                    ->defaultValue([])
                ->end()
                ->floatNode('timeout')
                    ->info('Seconds a client tool may run; null, no limit.')
                    ->defaultNull()
                ->end()
                ->scalarNode('key')
                    ->info('The backup key, 32 random bytes in base64, such as "%env(PHIL_BACKUP_KEY)%"; null is refused unless allow_unencrypted is true.')
                    ->defaultNull()
                ->end()
                ->booleanNode('allow_unencrypted')
                    ->info("Whether backups may be written in clear when no key is given, and, with a key, whether the backups in clear written before it may still be restored and tested; a backup holds every person's data.")
                    ->defaultFalse()
                ->end()
                ->arrayNode('media')
                    ->info('Media directories to back up beside the database, each by a name of letters, digits, "-" and "_", such as { uploads: "%kernel.project_dir%/public/uploads" }.')
                    ->useAttributeAsKey('name')
                    ->normalizeKeys(false)
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                ->end()
                ->arrayNode('rotation')
                    ->info('How many backups to keep by each rule, after each phil:backup:create; all zero, every backup is kept.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('last')->info('The most recent backups.')->defaultValue(0)->min(0)->end()
                        ->integerNode('daily')->info('The most recent backup of each of the latest days.')->defaultValue(0)->min(0)->end()
                        ->integerNode('weekly')->info('The most recent backup of each of the latest ISO weeks.')->defaultValue(0)->min(0)->end()
                        ->integerNode('monthly')->info('The most recent backup of each of the latest months.')->defaultValue(0)->min(0)->end()
                    ->end()
                ->end()
                ->arrayNode('restore')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('production_environments')
                            ->info('Kernel environments in which a restore needs PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE set to the name of the database.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['prod'])
                        ->end()
                        ->integerNode('keep_before_restore')
                            ->info('How many of the backups taken before a restore, labelled before-restore, are kept once a restore succeeds.')
                            ->defaultValue(3)
                            ->min(1)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('verify')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('database')
                            ->info('The scratch database, on the same server, into which phil:backup:verify restores a MySQL, MariaDB or PostgreSQL backup; null, it is not restored.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
            ->end()
            ->validate()
                ->ifTrue(static fn (array $config): bool => null === $config['key'] && true !== $config['allow_unencrypted'])
                ->thenInvalid('Backups are encrypted: give phil_backup.key, or set phil_backup.allow_unencrypted to true to write them in clear.')
            ->end();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var array{connection: string, directory: string, binaries: array<string, string>, timeout: ?float, key: ?string, allow_unencrypted: bool, media: array<string, string>, rotation: array{last: int, daily: int, weekly: int, monthly: int}, restore: array{production_environments: list<string>, keep_before_restore: int}, verify: array{database: ?string}} $config */
        $rotation = $config['rotation'];

        // Built once here so that a bad media name fails the container build rather than the first backup.
        try {
            new MediaArchive($config['media']);
        } catch (InvalidBackupValue $invalidBackupValue) {
            throw new InvalidConfigurationException('phil_backup.media: '.$invalidBackupValue->getMessage(), 0, $invalidBackupValue);
        }

        $services = $container->services();
        $services->set(ProcessCommandRunner::class)->args([$config['binaries'], $config['timeout']]);
        $services->set(self::DATABASE_CLIENT, DatabaseClient::class)
            ->factory(DatabaseClients::forConnection(...))
            ->args([new Reference($config['connection']), new Reference(ProcessCommandRunner::class)]);
        $services->set(BackupDirectory::class)->args([$config['directory']]);
        $services->set(MediaArchive::class)->args([$config['media']]);
        $services->set(RestoreGuard::class)->args(['%kernel.environment%', $config['restore']['production_environments']]);

        $cipher = null;

        if (null !== $config['key']) {
            $services->set(BackupKey::class)->factory([BackupKey::class, 'fromBase64'])->args([$config['key']]);
            $services->set(BackupCipher::class)->args([new Reference(BackupKey::class)]);
            $cipher = new Reference(BackupCipher::class);
        }

        $policy = null;

        if (0 !== array_sum($rotation)) {
            $services->set(RotationPolicy::class)->args([$rotation['last'], $rotation['daily'], $rotation['weekly'], $rotation['monthly']]);
            $policy = new Reference(RotationPolicy::class);
        }

        $services->set(Backups::class)
            ->args([new Reference(self::DATABASE_CLIENT), new Reference(BackupDirectory::class), $cipher, new Reference(MediaArchive::class), $policy, $config['allow_unencrypted']])
            ->public();
        $services->set(CreateBackupCommand::class)->args([new Reference(Backups::class)])->tag('console.command');
        $services->set(RestoreBackupCommand::class)->args([new Reference(Backups::class), new Reference(RestoreGuard::class), $config['restore']['keep_before_restore']])->tag('console.command');
        $services->set(VerifyBackupCommand::class)->args([new Reference(Backups::class), $config['verify']['database']])->tag('console.command');
    }
}
