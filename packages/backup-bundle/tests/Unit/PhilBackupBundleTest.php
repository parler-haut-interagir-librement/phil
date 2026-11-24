<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phil\Backup\Backups;
use Phil\Backup\Database\SqliteClient;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Encryption\BackupKey;
use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Media\MediaArchive;
use Phil\Backup\Restore\RestoreGuard;
use Phil\Backup\Rotation\RotationPolicy;
use Phil\Backup\Storage\BackupDirectory;
use Phil\BackupBundle\Infrastructure\Console\CreateBackupCommand;
use Phil\BackupBundle\Infrastructure\Console\MonitorBackupCommand;
use Phil\BackupBundle\Infrastructure\Console\RestoreBackupCommand;
use Phil\BackupBundle\Infrastructure\Console\VerifyBackupCommand;
use Phil\BackupBundle\Infrastructure\Lock\BackupLock;
use Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner;
use Phil\BackupBundle\PhilBackupBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\FlockStore;

/**
 * The services the bundle declares from its configuration, and a container compiled with them.
 */
#[CoversClass(PhilBackupBundle::class)]
final class PhilBackupBundleTest extends TestCase
{
    public function testBackupsInClearAreRefusedUnlessAllowed(): void
    {
        foreach ([[], ['key' => null], ['allow_unencrypted' => false]] as $config) {
            try {
                $this->load($config, allowUnencrypted: false);
                self::fail(json_encode($config, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertSame('Invalid configuration for path "phil_backup": Backups are encrypted: give phil_backup.key, or set phil_backup.allow_unencrypted to true to write them in clear.', $invalidConfigurationException->getMessage());
            }
        }

        $encrypted = $this->load(['key' => '%env(PHIL_BACKUP_KEY)%'], allowUnencrypted: false);
        self::assertTrue($encrypted->hasDefinition(BackupCipher::class));
        self::assertFalse($encrypted->getDefinition(Backups::class)->getArgument(5), 'With a key, the sets in clear are refused.');
        $migrating = $this->load(['key' => '%env(PHIL_BACKUP_KEY)%', 'allow_unencrypted' => true], allowUnencrypted: false);
        self::assertTrue($migrating->hasDefinition(BackupCipher::class), 'A key is used whenever it is given.');
        self::assertTrue($migrating->getDefinition(Backups::class)->getArgument(5), 'Allowed, the sets in clear written before the key are still read.');
        self::assertFalse($this->load(['allow_unencrypted' => true], allowUnencrypted: false)->hasDefinition(BackupCipher::class));
    }

    public function testTheDefaultsKeepEveryBackup(): void
    {
        $container = $this->load([]);

        $logger = new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        $dispatcher = new Reference('event_dispatcher', ContainerInterface::NULL_ON_INVALID_REFERENCE);
        self::assertEquals([[], null, 3600.0, 10.0, $logger, $dispatcher], $container->getDefinition(ProcessCommandRunner::class)->getArguments());
        self::assertSame([['channel' => 'backup']], $container->getDefinition(ProcessCommandRunner::class)->getTag('monolog.logger'));
        self::assertEquals([new Reference('database_connection'), new Reference(ProcessCommandRunner::class)], $container->getDefinition(PhilBackupBundle::DATABASE_CLIENT)->getArguments());
        self::assertSame(['%kernel.project_dir%/var/backup'], $container->getDefinition(BackupDirectory::class)->getArguments());
        self::assertSame([[]], $container->getDefinition(MediaArchive::class)->getArguments());
        self::assertSame(['%kernel.environment%', ['prod']], $container->getDefinition(RestoreGuard::class)->getArguments());
        self::assertFalse($container->hasDefinition(BackupCipher::class));
        self::assertFalse($container->hasDefinition(RotationPolicy::class));
        self::assertEquals([new Reference(PhilBackupBundle::DATABASE_CLIENT), new Reference(BackupDirectory::class), null, new Reference(MediaArchive::class), null, true], $container->getDefinition(Backups::class)->getArguments());
        self::assertTrue($container->getDefinition(Backups::class)->isPublic());
        $lock = new Reference(BackupLock::class);
        self::assertEquals([new Reference(Backups::class), null, $lock], $container->getDefinition(VerifyBackupCommand::class)->getArguments());
        self::assertEquals([new Reference(Backups::class), new Reference(RestoreGuard::class), 3, $lock], $container->getDefinition(RestoreBackupCommand::class)->getArguments());
        self::assertEquals([new Reference(Backups::class), $lock], $container->getDefinition(CreateBackupCommand::class)->getArguments());
        self::assertEquals([new Reference(Backups::class), 26, new Reference('clock', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(MonitorBackupCommand::class)->getArguments());
        self::assertEquals([new Reference(PhilBackupBundle::LOCK_FACTORY), '%kernel.project_dir%/var/backup', 86400.0], $container->getDefinition(BackupLock::class)->getArguments());
        self::assertSame(LockFactory::class, $container->getDefinition(PhilBackupBundle::LOCK_FACTORY)->getClass());
        self::assertEquals([new Definition(FlockStore::class)], $container->getDefinition(PhilBackupBundle::LOCK_FACTORY)->getArguments());

        foreach ([CreateBackupCommand::class, RestoreBackupCommand::class, VerifyBackupCommand::class, MonitorBackupCommand::class] as $command) {
            self::assertTrue($container->getDefinition($command)->hasTag('console.command'), $command);
        }

        self::assertSame('phil_backup.database_client', PhilBackupBundle::DATABASE_CLIENT);
        self::assertSame('phil_backup.lock_factory', PhilBackupBundle::LOCK_FACTORY);
    }

    public function testTheLockMayUseAnotherFactoryOrBeDisabled(): void
    {
        $shared = $this->load(['directory' => '/backups', 'lock' => ['factory' => 'lock.factory', 'ttl' => 7200], 'monitor' => ['max_age_hours' => 50]]);
        self::assertEquals([new Reference('lock.factory'), '/backups', 7200.0], $shared->getDefinition(BackupLock::class)->getArguments());
        self::assertFalse($shared->hasDefinition(PhilBackupBundle::LOCK_FACTORY));
        self::assertSame(50, $shared->getDefinition(MonitorBackupCommand::class)->getArgument(1));

        foreach ([['lock' => false], ['lock' => ['enabled' => false]]] as $config) {
            $unlocked = $this->load($config);
            self::assertFalse($unlocked->hasDefinition(BackupLock::class));
            self::assertFalse($unlocked->hasDefinition(PhilBackupBundle::LOCK_FACTORY));
            self::assertNull($unlocked->getDefinition(CreateBackupCommand::class)->getArgument(1));
            self::assertNull($unlocked->getDefinition(RestoreBackupCommand::class)->getArgument(3));
            self::assertNull($unlocked->getDefinition(VerifyBackupCommand::class)->getArgument(2));
        }
    }

    public function testTheConfigurationReachesTheServices(): void
    {
        $container = $this->load([
            'connection' => 'doctrine.dbal.legacy_connection',
            'directory' => '/backups',
            'binaries' => ['pg_dump' => '/opt/pg/bin/pg_dump', 'mariadb-dump' => '/opt/mariadb/bin/mariadb-dump'],
            'timeout' => 600,
            'warn_after' => null,
            'grace_period' => 30,
            'key' => '%env(PHIL_BACKUP_KEY)%',
            'media' => ['my-uploads' => '/srv/uploads', 'Documents_2' => '/srv/documents'],
            'rotation' => ['last' => 3, 'daily' => 0, 'weekly' => 0, 'monthly' => 12],
            'restore' => ['production_environments' => ['prod', 'live'], 'keep_before_restore' => 1],
            'verify' => ['database' => 'shop_restore_test'],
        ]);

        self::assertSame([['pg_dump' => '/opt/pg/bin/pg_dump', 'mariadb-dump' => '/opt/mariadb/bin/mariadb-dump'], 600, 0.0, 30], \array_slice($container->getDefinition(ProcessCommandRunner::class)->getArguments(), 0, 4));
        self::assertEquals(new Reference('doctrine.dbal.legacy_connection'), $container->getDefinition(PhilBackupBundle::DATABASE_CLIENT)->getArgument(0));
        self::assertSame(['/backups'], $container->getDefinition(BackupDirectory::class)->getArguments());
        self::assertSame([['my-uploads' => '/srv/uploads', 'Documents_2' => '/srv/documents']], $container->getDefinition(MediaArchive::class)->getArguments());
        self::assertSame(['%kernel.environment%', ['prod', 'live']], $container->getDefinition(RestoreGuard::class)->getArguments());
        self::assertSame([3, 0, 0, 12], $container->getDefinition(RotationPolicy::class)->getArguments());
        self::assertSame([BackupKey::class, 'fromBase64'], $container->getDefinition(BackupKey::class)->getFactory());
        self::assertSame('%env(PHIL_BACKUP_KEY)%', $container->getDefinition(BackupKey::class)->getArgument(0));
        self::assertEquals([new Reference(BackupKey::class)], $container->getDefinition(BackupCipher::class)->getArguments());
        self::assertEquals(new Reference(BackupCipher::class), $container->getDefinition(Backups::class)->getArgument(2));
        self::assertEquals(new Reference(RotationPolicy::class), $container->getDefinition(Backups::class)->getArgument(4));
        self::assertSame('shop_restore_test', $container->getDefinition(VerifyBackupCommand::class)->getArgument(1));
        self::assertSame(1, $container->getDefinition(RestoreBackupCommand::class)->getArgument(2));
    }

    public function testZeroIsAcceptedForEachDuration(): void
    {
        self::assertSame([[], 0, 0, 0], \array_slice($this->load(['timeout' => 0, 'warn_after' => 0, 'grace_period' => 0])->getDefinition(ProcessCommandRunner::class)->getArguments(), 0, 4));
    }

    public function testARuleMayKeepNone(): void
    {
        self::assertSame([0, 0, 0, 1], $this->load(['rotation' => ['last' => 0, 'daily' => 0, 'weekly' => 0, 'monthly' => 1]])->getDefinition(RotationPolicy::class)->getArguments());
        self::assertSame([1, 0, 0, 0], $this->load(['rotation' => ['last' => 1]])->getDefinition(RotationPolicy::class)->getArguments());
        self::assertSame([0, 1, 0, 0], $this->load(['rotation' => ['daily' => 1]])->getDefinition(RotationPolicy::class)->getArguments());
        self::assertSame([0, 0, 1, 0], $this->load(['rotation' => ['weekly' => 1, 'monthly' => 0]])->getDefinition(RotationPolicy::class)->getArguments());
    }

    public function testWhatTheBundleCannotUseFailsTheBuild(): void
    {
        foreach ([
            ['media' => ['up loads' => '/srv/uploads']],
            ['media' => ['uploads' => '']],
            ['rotation' => ['last' => -1]],
            ['rotation' => ['daily' => -1]],
            ['rotation' => ['weekly' => -1]],
            ['rotation' => ['monthly' => -1]],
            ['connection' => ''],
            ['directory' => ''],
            ['restore' => ['keep_before_restore' => 0]],
            ['timeout' => -1],
            ['warn_after' => -1],
            ['grace_period' => -1],
            ['lock' => ['ttl' => 0]],
            ['monitor' => ['max_age_hours' => 0]],
        ] as $config) {
            try {
                $this->load($config);
                self::fail(json_encode($config, \JSON_THROW_ON_ERROR));
            } catch (InvalidConfigurationException) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $this->load(['media' => ['up loads' => '/srv/uploads']]);
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertSame('phil_backup.media: The media name "up loads" holds characters other than letters, digits, "-" and "_".', $invalidConfigurationException->getMessage());
            self::assertInstanceOf(InvalidBackupValue::class, $invalidConfigurationException->getPrevious());
            self::assertSame(0, $invalidConfigurationException->getCode());
        }
    }

    public function testACompiledContainerBuildsTheServicesWithoutConnecting(): void
    {
        $path = sys_get_temp_dir().'/phil-backup-'.bin2hex(random_bytes(6)).'.sqlite';
        $key = BackupKey::generate()->toBase64();
        $container = $this->load(['key' => $key, 'rotation' => ['last' => 2], 'media' => ['uploads' => '/srv/uploads']]);
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->register('database_connection', Connection::class)->setFactory([DriverManager::class, 'getConnection'])->setArguments([['driver' => 'pdo_sqlite', 'path' => $path]]);

        foreach ([PhilBackupBundle::DATABASE_CLIENT, CreateBackupCommand::class, RestoreBackupCommand::class, VerifyBackupCommand::class, MonitorBackupCommand::class, BackupLock::class] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }

        $container->compile(true);

        $backups = $container->get(Backups::class);
        self::assertInstanceOf(Backups::class, $backups);
        self::assertInstanceOf(SqliteClient::class, $container->get(PhilBackupBundle::DATABASE_CLIENT));
        self::assertSame($path, $backups->client()->identity()->name);
        self::assertSame(sys_get_temp_dir().'/var/backup', $backups->directory()->path());
        self::assertInstanceOf(RestoreBackupCommand::class, $container->get(RestoreBackupCommand::class));
        self::assertInstanceOf(CreateBackupCommand::class, $container->get(CreateBackupCommand::class));
        self::assertInstanceOf(VerifyBackupCommand::class, $container->get(VerifyBackupCommand::class));
        self::assertInstanceOf(MonitorBackupCommand::class, $container->get(MonitorBackupCommand::class));
        self::assertInstanceOf(BackupLock::class, $container->get(BackupLock::class));
        self::assertFileDoesNotExist($path, 'Building the client opens no connection.');
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config, bool $allowUnencrypted = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $extension = new PhilBackupBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$allowUnencrypted ? $config + ['allow_unencrypted' => true] : $config], $container);

        return $container;
    }
}
