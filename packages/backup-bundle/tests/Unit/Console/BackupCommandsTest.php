<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit\Console;

use DateTimeImmutable;
use Phil\Backup\Backups;
use Phil\Backup\Database\MysqlClient;
use Phil\Backup\Database\SqliteClient;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Encryption\BackupKey;
use Phil\Backup\Media\MediaArchive;
use Phil\Backup\Restore\RestoreGuard;
use Phil\Backup\Rotation\RotationPolicy;
use Phil\Backup\Storage\BackupDirectory;
use Phil\BackupBundle\Infrastructure\Console\CreateBackupCommand;
use Phil\BackupBundle\Infrastructure\Console\RestoreBackupCommand;
use Phil\BackupBundle\Infrastructure\Console\VerifyBackupCommand;
use Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner;
use Phil\BackupBundle\Tests\Unit\Fixture\Files;
use Phil\BackupBundle\Tests\Unit\Fixture\SilentRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The three commands on a SQLite file, with the real `sqlite3`.
 */
#[CoversClass(CreateBackupCommand::class)]
#[CoversClass(RestoreBackupCommand::class)]
#[CoversClass(VerifyBackupCommand::class)]
final class BackupCommandsTest extends TestCase
{
    private string $directory;

    private string $database;

    private Backups $backups;

    private BackupKey $key;

    protected function setUp(): void
    {
        $this->directory = Files::directory();
        $this->database = $this->directory.'/app.sqlite';
        $this->key = BackupKey::generate();
        $this->sql("CREATE TABLE member (id INTEGER PRIMARY KEY, name TEXT); INSERT INTO member VALUES (1, 'Ada'), (2, 'Bob');");
        mkdir($this->directory.'/uploads');
        file_put_contents($this->directory.'/uploads/poster.jpg', 'jpeg');
        $this->backups = $this->backups();
    }

    protected function tearDown(): void
    {
        unset($_SERVER[RestoreGuard::VARIABLE], $_ENV[RestoreGuard::VARIABLE]);
        Files::remove($this->directory);
    }

    public function testABackupIsCreatedThenTheExpiredOnesAreRemoved(): void
    {
        mkdir($this->directory.'/backup');
        touch($this->directory.'/backup/app-20200101-000000.sqlite.enc');
        touch($this->directory.'/backup/app-20200101-000000.media.tar.enc');
        $command = new CreateBackupCommand($this->backups(new RotationPolicy(last: 1)));
        $tester = new CommandTester($command);
        self::assertSame('phil:backup:create', $command->getName());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        [$database, $media] = [$this->only('app-*.sqlite.enc'), $this->only('app-*.media.tar.enc')];
        self::assertSame(\sprintf("The sqlite database %s is backed up in %s.\nThe media files are archived in %s.\nThe expired backup app-20200101-000000 is removed.\n", $this->database, $database, $media), $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['--label' => 'before-migration', '--no-media' => true, '--no-rotate' => true]));
        $labelled = $this->only('app-*-before-migration.sqlite.enc');
        self::assertSame(\sprintf("The sqlite database %s is backed up in %s.\n", $this->database, $labelled), $tester->getDisplay());
        self::assertFileExists($database, 'Nothing is rotated with --no-rotate.');

        self::assertSame(Command::FAILURE, $tester->execute(['--label' => 'Before Migration']));
        self::assertSame("The label \"Before Migration\" holds characters other than lowercase letters, digits and \"-\", or starts with \"-\".\n", $tester->getDisplay());
    }

    public function testARestoreIsRefusedUntilEveryCheckPasses(): void
    {
        $tester = new CommandTester($this->restore('dev'));

        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertSame("There is no backup to restore in the backup directory.\n", $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute(['file' => $this->directory.'/missing.sqlite']));
        self::assertSame(\sprintf("The backup %s/missing.sqlite does not exist.\n", $this->directory), $tester->getDisplay());

        $set = $this->backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));

        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertSame(\sprintf("Nothing was restored: add --confirm to replace the content of the sqlite database %s with %s.\n", $this->database, $set->database), $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute(['--confirm' => true], ['interactive' => false]));
        self::assertSame("Nothing was restored: type the name of the database again, app.sqlite, with --database.\n", $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute(['--confirm' => true, '--database' => 'app']));
        self::assertSame("Nothing was restored: \"app\" is not the name of the database, app.sqlite.\n", $tester->getDisplay());

        $production = new CommandTester($this->restore('prod'));
        self::assertSame(Command::INVALID, $production->execute(['--confirm' => true, '--database' => 'app.sqlite']));
        self::assertSame("A restore is refused in the \"prod\" environment: set PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE to the name of the database, app.sqlite, for this run alone.\n", $production->getDisplay());

        $production->setInputs(['app.sqlite']);
        self::assertSame(Command::INVALID, $production->execute(['--confirm' => true]));
        self::assertStringStartsWith('A restore is refused in the "prod" environment', $production->getDisplay(), 'Production is refused before the name is asked for.');

        $_SERVER[RestoreGuard::VARIABLE] = '1';
        $_ENV[RestoreGuard::VARIABLE] = 'app.sqlite';
        self::assertSame(Command::INVALID, $production->execute(['--confirm' => true, '--database' => 'app.sqlite']));
        self::assertStringStartsWith('A restore is refused in the "prod" environment', $production->getDisplay(), 'The process environment comes first.');
        self::assertCount(2, Files::glob($this->directory.'/backup/*'), 'No backup is taken before a refused restore.');
    }

    public function testARestoreBacksTheCurrentContentUpFirst(): void
    {
        $set = $this->backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));
        $this->sql("UPDATE member SET name = 'changed';");
        file_put_contents($this->directory.'/uploads/poster.jpg', 'changed');

        $_ENV[RestoreGuard::VARIABLE] = 'app.sqlite';
        $command = $this->restore('prod');
        $tester = new CommandTester($command);
        self::assertSame('phil:backup:restore', $command->getName());
        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true, '--database' => 'app.sqlite']));

        $before = $this->only('app-*-before-restore.sqlite.enc');
        self::assertSame(\sprintf("The current content is backed up in %s first.\nThe sqlite database %s is restored from %s.\n1 media file(s) are restored from %s.\n", $before, $this->database, $set->database, $set->media), $tester->getDisplay());
        self::assertSame("Ada\nBob\n", $this->sql('SELECT name FROM member ORDER BY id;'));
        self::assertSame('jpeg', file_get_contents($this->directory.'/uploads/poster.jpg'));
        self::assertFileExists((string) preg_replace('/\.sqlite\.enc$/', '.media.tar.enc', $before), 'The media files are backed up too.');

        $beforeSet = $this->backups->find($before);
        self::assertNotNull($beforeSet);
        $this->backups->restore($beforeSet);
        self::assertSame("changed\nchanged\n", $this->sql('SELECT name FROM member ORDER BY id;'), 'The backup taken first holds the content that was replaced.');
        self::assertSame('changed', file_get_contents($this->directory.'/uploads/poster.jpg'));
    }

    public function testARestoreMayPruneTheMediaAndKeepsTheLatestBackupsTakenFirst(): void
    {
        $set = $this->backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));
        self::assertDirectoryExists($this->directory.'/backup', 'The backup made the directory.');

        foreach (['20261101-000000', '20261102-000000', '20261103-000000'] as $time) {
            touch(\sprintf('%s/backup/app-%s-before-restore.sqlite.enc', $this->directory, $time));
        }

        file_put_contents($this->directory.'/uploads/added.jpg', 'added');
        $tester = new CommandTester(new RestoreBackupCommand($this->backups, new RestoreGuard('dev'), 2));

        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true, '--database' => 'app.sqlite', '--prune' => true]));
        $before = $this->files('app-*-before-restore*.sqlite.enc', 2)[1];
        self::assertSame(\sprintf("The current content is backed up in %s first.\nThe sqlite database %s is restored from %s.\n1 media file(s) are restored from %s.\n1 media file(s) the backup does not hold are removed.\nThe older backup app-20261101-000000-before-restore is removed.\nThe older backup app-20261102-000000-before-restore is removed.\n", $before, $this->database, $set->database, $set->media), $tester->getDisplay());
        self::assertFileDoesNotExist($this->directory.'/uploads/added.jpg');
        self::assertSame([$this->directory.'/backup/app-20261103-000000-before-restore.sqlite.enc', $before], $this->files('app-*-before-restore*.sqlite.enc', 2));

        file_put_contents($this->directory.'/uploads/added.jpg', 'added');
        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true, '--database' => 'app.sqlite', '--prune' => true, '--no-media' => true]));
        self::assertStringNotContainsString('media file(s)', $tester->getDisplay(), 'Without the media, nothing is pruned.');
        self::assertFileExists($this->directory.'/uploads/added.jpg');
        self::assertNotContains($this->directory.'/backup/app-20261103-000000-before-restore.sqlite.enc', $this->files('app-*-before-restore*.sqlite.enc', 2));

        touch($this->directory.'/backup/app-20261001-000000-before-restore.sqlite.enc');
        self::assertSame(Command::SUCCESS, new CommandTester($this->restore('dev'))->execute(['--confirm' => true, '--database' => 'app.sqlite']));
        self::assertFileExists($this->directory.'/uploads/added.jpg', 'Without --prune, a file the backup does not hold is left.');
        self::assertCount(3, $this->files('app-*-before-restore*.sqlite.enc', 3), 'Three are kept by default.');
        self::assertFileDoesNotExist($this->directory.'/backup/app-20261001-000000-before-restore.sqlite.enc', 'The oldest of four is removed.');
    }

    public function testTheNameIsAskedForWhenTheConsoleIsInteractive(): void
    {
        $set = $this->backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));
        $this->sql("UPDATE member SET name = 'changed';");
        $_SERVER[RestoreGuard::VARIABLE] = 'app.sqlite';
        $tester = new CommandTester($this->restore('prod'));

        $tester->setInputs(['app']);
        self::assertSame(Command::INVALID, $tester->execute(['--confirm' => true, '--no-media' => true]));
        self::assertStringContainsString(\sprintf('The sqlite database %s will be replaced with %s. Type its name, app.sqlite, to go on', $this->database, $set->database), $tester->getDisplay());
        self::assertStringEndsWith("Nothing was restored: \"app\" is not the name of the database, app.sqlite.\n", $tester->getDisplay());

        $tester->setInputs(['app.sqlite']);
        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true, '--no-media' => true]));
        self::assertStringEndsWith(\sprintf("The sqlite database %s is restored from %s.\n", $this->database, $set->database), $tester->getDisplay());
        self::assertSame("Ada\nBob\n", $this->sql('SELECT name FROM member ORDER BY id;'));
        self::assertCount(1, Files::glob($this->directory.'/backup/*-before-restore.sqlite.enc'), 'The refused attempt took no backup.');
        self::assertCount(0, Files::glob($this->directory.'/backup/*-before-restore.media.tar.enc'), 'Without the media, the backup taken first has none.');

        $tester->setInputs(['']);
        self::assertSame(Command::INVALID, $tester->execute(['--confirm' => true]));
        self::assertStringEndsWith("Nothing was restored: type the name of the database again, app.sqlite, with --database.\n", $tester->getDisplay());
    }

    public function testAFailedRestoreSaysWhy(): void
    {
        $set = $this->backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));
        $tester = new CommandTester(new RestoreBackupCommand($this->backups(key: BackupKey::generate()), new RestoreGuard('dev')));

        self::assertSame(Command::FAILURE, $tester->execute(['file' => $set->database, '--confirm' => true, '--database' => 'app.sqlite']));
        self::assertStringEndsWith(\sprintf("%s cannot be decrypted: the key is not the one it was encrypted with, or the file is damaged.\n", $set->media), $tester->getDisplay());
        self::assertStringStartsWith('The current content is backed up in ', $tester->getDisplay());
    }

    public function testABackupIsTestedWithoutTouchingTheDatabase(): void
    {
        $command = new VerifyBackupCommand($this->backups);
        $tester = new CommandTester($command);
        self::assertSame('phil:backup:verify', $command->getName());

        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertSame("There is no backup to test in the backup directory.\n", $tester->getDisplay());

        $set = $this->backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));
        $this->sql("UPDATE member SET name = 'changed';");

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertMatchesRegularExpression('#^The files of app-20261107-031500 decrypt and authenticate\.\nThe database of app-20261107-031500 restores: it was restored into the sqlite database '.preg_quote($this->directory, '#').'/backup/\.phil-backup-\w+ and checked\.\nThe media archive of app-20261107-031500 reads whole: 1 file\(s\)\.\n$#', $tester->getDisplay());
        self::assertSame("changed\nchanged\n", $this->sql('SELECT name FROM member ORDER BY id;'));

        file_put_contents($set->database, 'damaged', \FILE_APPEND);
        self::assertSame(Command::FAILURE, $tester->execute(['file' => $set->database]));
        self::assertSame(\sprintf("%s cannot be decrypted: the key is not the one it was encrypted with, or the file is damaged.\n", $set->database), $tester->getDisplay());
    }

    public function testAServerBackupIsRestoredOnlyIntoAScratchDatabase(): void
    {
        $runner = new SilentRunner();
        $backups = new Backups(new MysqlClient('shop', [], $runner, false), new BackupDirectory($this->directory.'/backup'));
        file_put_contents($this->directory.'/shop.sql', '-- dump');

        $tester = new CommandTester(new VerifyBackupCommand($backups));
        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $this->directory.'/shop.sql']));
        self::assertSame("The database of shop.sql was not restored: give phil_backup.verify.database a scratch database to restore it into.\n", $tester->getDisplay());

        $tester = new CommandTester(new VerifyBackupCommand($backups, 'shop_restore_test'));
        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $this->directory.'/shop.sql']));
        self::assertSame("The database of shop.sql restores: it was restored into the mysql database shop_restore_test and checked.\n", $tester->getDisplay());
        self::assertSame(['--system-command=OFF', 'shop_restore_test'], \array_slice($runner->commands[0], 2));
    }

    private function backups(?RotationPolicy $rotation = null, ?BackupKey $key = null): Backups
    {
        return new Backups(
            new SqliteClient($this->database, new ProcessCommandRunner()),
            new BackupDirectory($this->directory.'/backup'),
            new BackupCipher($key ?? $this->key),
            new MediaArchive(['uploads' => $this->directory.'/uploads']),
            $rotation,
        );
    }

    private function restore(string $environment): RestoreBackupCommand
    {
        return new RestoreBackupCommand($this->backups, new RestoreGuard($environment));
    }

    private function only(string $pattern): string
    {
        return $this->files($pattern, 1)[0];
    }

    /**
     * @return list<string>
     */
    private function files(string $pattern, int $count): array
    {
        $files = Files::glob($this->directory.'/backup/'.$pattern);
        self::assertCount($count, $files, $pattern);

        return $files;
    }

    private function sql(string $sql): string
    {
        return new ProcessCommandRunner()->run(['sqlite3', $this->database, $sql]);
    }
}
