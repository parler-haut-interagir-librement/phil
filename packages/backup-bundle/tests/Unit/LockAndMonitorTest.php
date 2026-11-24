<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\Backup\Backups;
use Phil\Backup\Database\SqliteClient;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Encryption\BackupKey;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Storage\BackupDirectory;
use Phil\BackupBundle\Infrastructure\Console\MonitorBackupCommand;
use Phil\BackupBundle\Infrastructure\Lock\BackupLock;
use Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner;
use Phil\BackupBundle\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\FlockStore;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * The lock of a backup directory, on a flock store and in memory, and the freshness of the most recent backup.
 */
#[CoversClass(BackupLock::class)]
#[CoversClass(MonitorBackupCommand::class)]
final class LockAndMonitorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = Files::directory();
    }

    protected function tearDown(): void
    {
        Files::remove($this->directory);
    }

    public function testTheLockIsNamedAfterTheDirectory(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        mkdir($this->directory.'/backup');
        $lock = new BackupLock($factory, $this->directory.'/backup');

        self::assertSame('phil_backup:', BackupLock::PREFIX);
        self::assertSame('phil_backup:'.hash('sha256', (string) realpath($this->directory.'/backup')), $lock->resource());
        self::assertSame($lock->resource(), new BackupLock($factory, $this->directory.'/backup/../backup')->resource(), 'the same directory by another path');
        self::assertSame('phil_backup:'.hash('sha256', '/nowhere/backup'), new BackupLock($factory, '/nowhere/backup')->resource(), 'a directory not created yet, by its path');
        self::assertNotSame($lock->resource(), new BackupLock($factory, $this->directory)->resource());
    }

    public function testTheWorkRunsWithTheLockHeldThenFreesIt(): void
    {
        $factory = new LockFactory(new FlockStore($this->directory));
        $lock = new BackupLock($factory, '/srv/backup', 60.0);
        $other = $factory->createLock($lock->resource());

        self::assertSame('done', $lock->exclusively(static function () use ($other): string {
            self::assertFalse($other->acquire(), 'Held during the work.');

            return 'done';
        }));
        self::assertTrue($other->acquire(), 'Freed after the work.');

        try {
            $lock->exclusively($this->failing('Never run.'));
            self::fail('Run while held.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('Another backup, rotation, restore or restore test of /srv/backup is running: try again once it ends.', $backupFailed->getMessage());
        }

        $other->release();

        try {
            $lock->exclusively($this->failing('The work failed.'));
            self::fail('No failure.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The work failed.', $runtimeException->getMessage());
        }

        self::assertTrue($other->acquire(), 'Freed after a failure too.');
        $other->release();
    }

    public function testALockThatCannotBeTakenSaysWhy(): void
    {
        $lock = new BackupLock(new LockFactory(new class implements PersistingStoreInterface {
            public function save(Key $key): void
            {
                throw new RuntimeException('the store is down');
            }

            public function delete(Key $key): void
            {
            }

            public function exists(Key $key): bool
            {
                return false;
            }

            public function putOffExpiration(Key $key, float $ttl): void
            {
            }
        }), '/srv/backup');

        $this->expectExceptionObject(BackupFailed::because('The lock of the backups in /srv/backup cannot be taken: Failed to acquire the "'.$lock->resource().'" lock.'));

        $lock->exclusively(static fn (): string => 'never');
    }

    public function testTheMostRecentBackupIsFreshForTheHoursGiven(): void
    {
        $backups = $this->backups();
        $clock = new MockClock('2026-11-08 05:14:59 UTC');
        $command = new MonitorBackupCommand($backups, clock: $clock);
        self::assertSame('phil:backup:monitor', $command->getName());
        self::assertSame(26, MonitorBackupCommand::DEFAULT_MAX_AGE_HOURS);

        self::assertSame([Command::FAILURE, "There is no backup in the backup directory.\n"], $this->monitor($command));

        $backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));
        $backups->create(new DateTimeImmutable('2026-11-08 03:15:00 UTC'), 'before-migration');

        self::assertSame([Command::SUCCESS, "The most recent backup, app-20261107-031500, was taken 25 h 59 min ago: less than 26 hour(s).\n"], $this->monitor($command));

        $clock->modify('+1 second');
        self::assertSame([Command::FAILURE, "The most recent backup, app-20261107-031500, was taken 26 h 00 min ago: more than 26 hour(s).\n"], $this->monitor($command));
        self::assertSame(Command::SUCCESS, $this->monitor($command, ['--max-age' => '27'])[0]);
        self::assertSame(Command::SUCCESS, $this->monitor(new MonitorBackupCommand($backups, 48, $clock))[0]);
        self::assertSame(Command::FAILURE, $this->monitor(new MonitorBackupCommand($backups, 48, $clock), ['--max-age' => '1'])[0]);

        $clock->modify('-2 days');
        self::assertSame([Command::SUCCESS, "The most recent backup, app-20261107-031500, was taken 0 h 00 min ago: less than 26 hour(s).\n"], $this->monitor($command), 'a clock behind the backup counts no time');
    }

    public function testAMaximumAgeIsAWholeNumberOfHours(): void
    {
        $command = new MonitorBackupCommand($this->backups());

        foreach (['0', '-1', '1.5', 'a day', '', '01', '1000000', "1\n"] as $hours) {
            self::assertSame([Command::INVALID, "The maximum age is a whole number of hours, 1 or more.\n"], $this->monitor($command, ['--max-age' => $hours]), $hours);
        }

        self::assertSame(Command::FAILURE, $this->monitor($command, ['--max-age' => '1'])[0]);
        self::assertSame(Command::FAILURE, $this->monitor($command, ['--max-age' => '999999'])[0], 'no backup yet');
    }

    public function testWithoutAClockTheTimeIsNow(): void
    {
        $backups = $this->backups();
        $backups->create(new DateTimeImmutable('-1 hour'));

        self::assertSame(Command::SUCCESS, $this->monitor(new MonitorBackupCommand($backups, 2))[0]);
        self::assertSame(Command::FAILURE, $this->monitor(new MonitorBackupCommand($backups, 1))[0]);
    }

    public function testWithAKeyASetInClearIsNotCounted(): void
    {
        $backups = new Backups(new SqliteClient($this->directory.'/app.sqlite', new ProcessCommandRunner()), new BackupDirectory($this->directory.'/backup'), new BackupCipher(BackupKey::generate()));
        mkdir($this->directory.'/backup');
        touch($this->directory.'/backup/app-20261107-031500.sqlite');
        self::assertSame([Command::FAILURE, "There is no backup in the backup directory.\n"], $this->monitor(new MonitorBackupCommand($backups, clock: new MockClock('2026-11-07 04:00 UTC'))), 'with a key, a set in clear is never the most recent one');
    }

    /**
     * The exit code and the display of one run of the command.
     *
     * @param array<string, string> $input
     *
     * @return array{int, string}
     *
     * @phpstan-impure
     */
    private function monitor(MonitorBackupCommand $command, array $input = []): array
    {
        $tester = new CommandTester($command);
        $status = $tester->execute($input);

        return [$status, $tester->getDisplay()];
    }

    /**
     * Work that fails with the message.
     *
     * @return Closure(): string
     */
    private function failing(string $message): Closure
    {
        return static fn (): string => throw new RuntimeException($message);
    }

    private function backups(): Backups
    {
        $database = $this->directory.'/app.sqlite';
        new ProcessCommandRunner()->run(['sqlite3', $database, 'CREATE TABLE t (id INTEGER);']);

        return new Backups(new SqliteClient($database, new ProcessCommandRunner()), new BackupDirectory($this->directory.'/backup'), new BackupCipher(BackupKey::generate()));
    }
}
