<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Database;

use Phil\Backup\Database\DatabaseIdentity;
use Phil\Backup\Database\MysqlClient;
use Phil\Backup\Database\PostgresqlClient;
use Phil\Backup\Database\SqliteClient;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Tests\Unit\Fixture\FailingRunner;
use Phil\Backup\Tests\Unit\Fixture\RecordingRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The command lines each client builds, recorded without a database server.
 */
#[CoversClass(MysqlClient::class)]
#[CoversClass(PostgresqlClient::class)]
#[CoversClass(SqliteClient::class)]
#[CoversClass(DatabaseIdentity::class)]
#[CoversClass(BackupFailed::class)]
final class DatabaseClientsTest extends TestCase
{
    public function testMysqlReadsItsConnectionFromAnOptionFileOfItsOwner(): void
    {
        $runner = new RecordingRunner();
        $client = new MysqlClient('shop', ['host' => 'db.test', 'port' => '3307', 'user' => 'app', 'password' => 'p"a\ss'], $runner, false);

        self::assertSame('mysql database shop on db.test', $client->identity()->describe());
        self::assertSame('sql', $client->extension());

        $client->backup('/backups/shop.sql');
        $client->restore('/backups/old.sql');

        [$backup, $restore] = $runner->runs;
        $options = "[client]\nhost=\"db.test\"\nport=\"3307\"\nuser=\"app\"\npassword=\"p\\\"a\\\\ss\"\n# mode 600";
        self::assertSame(['/usr/bin/mysqldump', '--single-transaction', '--routines', '--triggers', '--result-file=/backups/shop.sql', 'shop'], [$backup['command'][0], ...\array_slice($backup['command'], 2)]);
        self::assertMatchesRegularExpression('#^--defaults-file=/.+/phil-backup-#', $backup['command'][1]);
        self::assertSame($options, $backup['options']);
        self::assertSame([], $backup['environment']);
        self::assertNull($backup['input']);
        self::assertSame(['/usr/bin/mysql', '--system-command=OFF', 'shop'], [$restore['command'][0], ...\array_slice($restore['command'], 2)]);
        self::assertSame(['/usr/bin/mysql'], $runner->helps, 'The client is asked whether it can turn its shell commands off.');
        self::assertSame('/backups/old.sql', $restore['input']);
        self::assertSame($options, $restore['options']);
        self::assertFileDoesNotExist(substr($backup['command'][1], 16), 'The option file is removed once the tool has run.');
        self::assertFileDoesNotExist(substr($restore['command'][1], 16));

        $scratch = $client->withDatabase('shop_restore_test');
        $scratch->restore('/backups/old.sql');
        $scratch->check();
        self::assertSame('mysql database shop_restore_test on db.test', $scratch->identity()->describe());
        self::assertSame('shop_restore_test', $runner->runs[2]['command'][3]);
        self::assertSame($options, $runner->runs[2]['options']);
        self::assertCount(3, $runner->runs, 'MySQL has no check of its own to run.');
    }

    public function testARestoreTurnsTheShellCommandsOfTheClientOff(): void
    {
        $mariadb = new RecordingRunner(help: "  --sandbox           Disallow commands that access the file system (except \\P without an argument and \\e).\n  --system-command\n");
        new MysqlClient('shop', [], $mariadb, true)->restore('a.sql');
        self::assertSame(['/usr/bin/mariadb', '--sandbox', 'shop'], [$mariadb->runs[0]['command'][0], ...\array_slice($mariadb->runs[0]['command'], 2)], 'The sandbox, which covers more, comes first.');

        $old = new RecordingRunner(help: "  --sandboxing\n  --system-commands\n  --batch\n");

        try {
            new MysqlClient('shop', [], $old, false)->restore('a.sql');
            self::fail('A client that runs shell commands is refused.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('/usr/bin/mysql cannot turn its shell commands off, which a dump could run on this machine: install MySQL 8.0.40 or later, or MariaDB 10.5.25 or later.', $backupFailed->getMessage());
        }

        self::assertSame([], $old->runs, 'Nothing is restored.');
    }

    public function testTheOptionFileIsRemovedWhenTheToolFails(): void
    {
        $runner = new FailingRunner();
        $client = new MysqlClient('shop', ['password' => 'secret'], $runner, false);

        try {
            $client->backup('a.sql');
            self::fail('The failure reaches the caller.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('mysqldump failed: Access denied', $backupFailed->getMessage());
        }

        self::assertNotNull($runner->optionFile);
        self::assertFileDoesNotExist($runner->optionFile);
    }

    public function testMariadbPrefersItsOwnToolsAndFallsBackOnMysqlOnes(): void
    {
        $own = new RecordingRunner();
        $client = new MysqlClient('shop', ['unix_socket' => '/run/mysqld.sock'], $own, true);
        $client->backup('a.sql');
        $client->restore('a.sql');
        $client->withDatabase('copy')->backup('b.sql');

        self::assertSame('mariadb database shop', $client->identity()->describe());
        self::assertSame(['/usr/bin/mariadb-dump', '/usr/bin/mariadb', '/usr/bin/mariadb-dump'], array_map(static fn (array $run): string => $run['command'][0], $own->runs));
        self::assertSame("[client]\nsocket=\"/run/mysqld.sock\"\n# mode 600", $own->runs[0]['options']);

        $mysql = new RecordingRunner(['mysqldump', 'mysql']);
        $client = new MysqlClient('shop', [], $mysql, true);
        $client->backup('a.sql');
        $client->restore('a.sql');
        self::assertSame(['/usr/bin/mysqldump', '/usr/bin/mysql'], array_map(static fn (array $run): string => $run['command'][0], $mysql->runs));
        self::assertSame("[client]\n# mode 600", $mysql->runs[0]['options']);

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains("mysqldump is not installed or not on the PATH: install your database's client tools, or give the path of the tool.");
        new MysqlClient('shop', [], new RecordingRunner(['mariadb-dump']), false)->backup('a.sql');
    }

    public function testPostgresqlReadsItsConnectionFromItsEnvironment(): void
    {
        $runner = new RecordingRunner();
        $client = new PostgresqlClient('shop', ['host' => 'db.test', 'port' => '5433', 'user' => 'app', 'password' => 'secret'], $runner);

        self::assertSame('postgresql database shop on db.test', $client->identity()->describe());
        self::assertSame('dump', $client->extension());

        $client->backup('/backups/shop.dump');
        $client->restore('/backups/old.dump');

        $scratch = $client->withDatabase('shop_test');
        $scratch->restore('/backups/old.dump');
        $scratch->check();

        $environment = ['PGHOST' => 'db.test', 'PGPORT' => '5433', 'PGUSER' => 'app', 'PGPASSWORD' => 'secret'];
        self::assertSame([
            ['command' => ['/usr/bin/pg_dump', '--format=custom', '--file=/backups/shop.dump', '--dbname=shop'], 'environment' => $environment, 'input' => null, 'options' => null],
            ['command' => ['/usr/bin/pg_restore', '--clean', '--if-exists', '--no-owner', '--single-transaction', '--dbname=shop', '/backups/old.dump'], 'environment' => $environment, 'input' => null, 'options' => null],
            ['command' => ['/usr/bin/pg_restore', '--clean', '--if-exists', '--no-owner', '--single-transaction', '--dbname=shop_test', '/backups/old.dump'], 'environment' => $environment, 'input' => null, 'options' => null],
        ], $runner->runs);

        $local = new RecordingRunner();
        new PostgresqlClient('shop', [], $local)->backup('a.dump');
        self::assertSame([], $local->runs[0]['environment']);
        self::assertSame('postgresql database shop', new PostgresqlClient('shop', [], $local)->identity()->describe());
    }

    public function testSqliteWorksOnItsFile(): void
    {
        $runner = new RecordingRunner(output: "ok\n");
        $client = new SqliteClient('/data/app.sqlite', $runner);

        self::assertSame('sqlite database /data/app.sqlite', $client->identity()->describe());
        self::assertSame('sqlite', $client->extension());

        $old = sys_get_temp_dir().'/phil-backup old '.bin2hex(random_bytes(4)).'.sqlite';
        touch($old);
        $client->backup('/backups/app.sqlite');
        $client->restore($old);
        $client->withDatabase('/tmp/copy.sqlite')->check();
        unlink($old);

        self::assertSame([
            ['/usr/bin/sqlite3', '/data/app.sqlite', ".backup '/backups/app.sqlite'"],
            ['/usr/bin/sqlite3', '/data/app.sqlite', ".restore '".$old."'"],
            ['/usr/bin/sqlite3', '/tmp/copy.sqlite', 'PRAGMA integrity_check;'],
        ], array_column($runner->runs, 'command'));
    }

    public function testASqliteCheckThatDoesNotAnswerOkFails(): void
    {
        foreach (["*** in database main ***\nPage 3: never used\n" => 'The restored SQLite database fails its integrity check: *** in database main ***'."\nPage 3: never used", "\n" => 'The restored SQLite database fails its integrity check: no answer.'] as $output => $message) {
            try {
                new SqliteClient('/data/app.sqlite', new RecordingRunner(output: $output))->check();
                self::fail($message);
            } catch (BackupFailed $backupFailed) {
                self::assertSame($message, $backupFailed->getMessage());
            }
        }
    }

    public function testASqliteRestoreNeedsItsBackupAndASafeFileName(): void
    {
        $runner = new RecordingRunner();
        $client = new SqliteClient('/data/app.sqlite', $runner);

        try {
            $client->restore('/no/such/backup.sqlite');
            self::fail('A missing backup is never restored: sqlite3 would create it empty.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('The backup /no/such/backup.sqlite does not exist.', $backupFailed->getMessage());
            self::assertSame([], $runner->runs);
        }

        foreach (["it's.sqlite", "a\n.shell rm -rf ~", "a\x7F", "a\x00", "a\x1F"] as $file) {
            foreach (['backup' => $client->backup(...), 'restore' => $client->restore(...)] as $method => $call) {
                try {
                    $call($file);
                    self::fail($method.' '.$file);
                } catch (BackupFailed $backupFailed) {
                    self::assertSame('sqlite3 cannot be given a file name that holds a quote or a control character.', $backupFailed->getMessage());
                }
            }
        }
    }

    public function testTheIdentityGivesTheNameToTypeAgain(): void
    {
        self::assertSame('app.sqlite', new DatabaseIdentity('sqlite', null, '/data/app.sqlite')->typedName());
        self::assertTrue(new DatabaseIdentity('sqlite', null, '/data/app.sqlite')->isFile());
        self::assertSame('shop', new DatabaseIdentity('mysql', 'db.test', 'shop')->typedName());
        self::assertFalse(new DatabaseIdentity('mysql', 'db.test', 'shop')->isFile());
        self::assertSame('a/b', new DatabaseIdentity('postgresql', null, 'a/b')->typedName());
    }

    public function testFailuresSayWhatToDo(): void
    {
        self::assertSame("mariadb-dump or mysqldump is not installed or not on the PATH: install your database's client tools, or give its path in the settings.", BackupFailed::missing(['mariadb-dump', 'mysqldump'], 'give its path in the settings')->getMessage());
        self::assertSame('pg_dump failed: no message.', BackupFailed::failed('pg_dump', " \n")->getMessage());
        self::assertSame('pg_dump failed: role "x" does not exist', BackupFailed::failed('pg_dump', "role \"x\" does not exist\n")->getMessage());
    }
}
