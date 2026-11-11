<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit;

use DateTimeImmutable;
use LogicException;
use Phil\Backup\Backups;
use Phil\Backup\Database\DatabaseIdentity;
use Phil\Backup\Database\MysqlClient;
use Phil\Backup\Database\SqliteClient;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Encryption\BackupKey;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Media\MediaArchive;
use Phil\Backup\Restoration;
use Phil\Backup\Rotation\RotationPolicy;
use Phil\Backup\Storage\BackupDirectory;
use Phil\Backup\Storage\BackupSet;
use Phil\Backup\Tests\Unit\Fixture\Files;
use Phil\Backup\Tests\Unit\Fixture\ProcessRunner;
use Phil\Backup\Tests\Unit\Fixture\RecordingRunner;
use Phil\Backup\Verification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The library in one object, on a real SQLite file with the real `sqlite3`.
 */
#[CoversClass(Backups::class)]
#[CoversClass(Restoration::class)]
#[CoversClass(Verification::class)]
final class BackupsTest extends TestCase
{
    private string $directory;

    private string $database;

    private SqliteClient $client;

    private BackupDirectory $backups;

    private MediaArchive $media;

    protected function setUp(): void
    {
        $this->directory = Files::directory();
        $this->database = $this->directory.'/app.sqlite';
        $this->client = new SqliteClient($this->database, new ProcessRunner());
        $this->sql("CREATE TABLE member (id INTEGER PRIMARY KEY, name TEXT); INSERT INTO member VALUES (1, 'Ada'), (2, 'Bob');");
        $this->backups = new BackupDirectory($this->directory.'/backup');
        mkdir($this->directory.'/uploads');
        file_put_contents($this->directory.'/uploads/poster.jpg', 'jpeg');
        $this->media = new MediaArchive(['uploads' => $this->directory.'/uploads']);
    }

    protected function tearDown(): void
    {
        Files::remove($this->directory);
    }

    public function testAnEncryptedSetRestoresTheDatabaseAndTheMedia(): void
    {
        $backups = new Backups($this->client, $this->backups, new BackupCipher(BackupKey::generate()), $this->media);
        self::assertSame($this->client, $backups->client());
        self::assertSame($this->backups, $backups->directory());

        $at = new DateTimeImmutable('2026-11-07 03:15:00 UTC');
        $set = $backups->create($at);

        self::assertSame(['app-20261107-031500', $at, ''], [$set->stem, $set->time, $set->label]);
        self::assertSame([$this->directory.'/backup/app-20261107-031500.sqlite.enc', $this->directory.'/backup/app-20261107-031500.media.tar.enc'], $set->files());
        self::assertSame([$set->media, $set->database], Files::glob($this->directory.'/backup/*'), 'No copy in clear is left.');
        self::assertSame(0o600, fileperms($set->database) & 0o777);
        self::assertStringStartsWith('PHILBAK', (string) file_get_contents($set->database));

        $this->sql("UPDATE member SET name = 'changed';");
        file_put_contents($this->directory.'/uploads/poster.jpg', 'changed');
        self::assertSame($set->stem, $backups->find(null)?->stem);
        self::assertEquals(new Restoration(1, 0), $backups->restore($backups->find($set->database) ?? $set));

        self::assertSame("Ada\nBob\n", $this->sql('SELECT name FROM member ORDER BY id;'));
        self::assertSame('jpeg', file_get_contents($this->directory.'/uploads/poster.jpg'));
        self::assertCount(2, Files::glob($this->directory.'/backup/*'));

        $this->sql("UPDATE member SET name = 'changed';");
        file_put_contents($this->directory.'/uploads/poster.jpg', 'changed');
        self::assertEquals(new Restoration(0, 0), $backups->restore($set, media: false, prune: true));
        self::assertSame("Ada\nBob\n", $this->sql('SELECT name FROM member ORDER BY id;'));
        self::assertSame('changed', file_get_contents($this->directory.'/uploads/poster.jpg'));
    }

    public function testASetInClearAndWithoutMedia(): void
    {
        $backups = new Backups($this->client, $this->backups, null, $this->media);
        $set = $backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'), 'before-migration', media: false);

        self::assertSame([$this->directory.'/backup/app-20261107-031500-before-migration.sqlite'], $set->files());
        self::assertSame(0o600, fileperms($set->database) & 0o777);
        self::assertStringStartsWith('SQLite format 3', (string) file_get_contents($set->database));
        self::assertNull($backups->find(null), 'A labelled set is restored by naming it.');

        $this->sql("UPDATE member SET name = 'changed';");
        self::assertEquals(new Restoration(0, 0), $backups->restore($set, prune: true));
        self::assertSame("Ada\nBob\n", $this->sql('SELECT name FROM member ORDER BY id;'));

        $plain = new Backups($this->client, $this->backups)->create(new DateTimeImmutable('2026-11-07 04:00:00 UTC'));
        self::assertNull($plain->media, 'Without media directories, a set is its database.');
    }

    public function testARestoreMayPruneTheMediaTheSetDoesNotHold(): void
    {
        $backups = new Backups($this->client, $this->backups, new BackupCipher(BackupKey::generate()), $this->media);
        $set = $backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));
        file_put_contents($this->directory.'/uploads/added.jpg', 'added');

        self::assertEquals(new Restoration(1, 0), $backups->restore($set));
        self::assertFileExists($this->directory.'/uploads/added.jpg', 'Without prune, a file the set does not hold is left.');

        $restoration = $backups->restore($set, prune: true);
        self::assertSame([1, 1], [$restoration->mediaFiles, $restoration->removedFiles]);
        self::assertFileDoesNotExist($this->directory.'/uploads/added.jpg');
        self::assertSame('jpeg', file_get_contents($this->directory.'/uploads/poster.jpg'));
        self::assertCount(2, Files::glob($this->directory.'/backup/*'), 'No copy in clear is left.');
    }

    public function testTheLatestSetsOfALabelAreKept(): void
    {
        $backups = new Backups($this->client, $this->backups, null, $this->media);
        $at = new DateTimeImmutable('2026-11-07 03:15:00 UTC');
        $plain = $backups->create($at);
        $sets = [$backups->create($at, 'before-restore'), $backups->create($at, 'before-restore'), $backups->create($at->modify('+1 second'), 'before-restore', media: false), $backups->create($at, 'before-restore')];
        $other = $backups->create($at, 'anonymized');

        self::assertSame(['app-20261107-031500-before-restore', 'app-20261107-031500-before-restore_2', 'app-20261107-031500-before-restore_3', 'app-20261107-031501-before-restore'], array_map(static fn (BackupSet $set): string => $set->stem, [$sets[0], $sets[1], $sets[3], $sets[2]]));
        self::assertSame([], $backups->keepLatest('before-restore', 4));
        self::assertSame(['app-20261107-031500-before-restore', 'app-20261107-031500-before-restore_2'], array_map(static fn (BackupSet $set): string => $set->stem, $backups->keepLatest('before-restore', 2)));

        foreach ([$sets[0], $sets[1]] as $removed) {
            self::assertFileDoesNotExist($removed->database);
            self::assertFileDoesNotExist((string) $removed->media);
        }

        foreach ([$plain, $sets[2], $sets[3], $other] as $kept) {
            self::assertFileExists($kept->database);
        }

        self::assertSame(['app-20261107-031500-before-restore_3'], array_map(static fn (BackupSet $set): string => $set->stem, $backups->keepLatest('before-restore', 1)));

        foreach ([['', 1], ['before-restore', 0]] as [$label, $keep]) {
            try {
                $backups->keepLatest($label, $keep);
                self::fail($label);
            } catch (InvalidBackupValue $invalidBackupValue) {
                self::assertSame('The sets kept by label are those of a label, at least one of them.', $invalidBackupValue->getMessage());
            }
        }
    }

    public function testAFailedBackupLeavesNoFile(): void
    {
        $failing = new SqliteClient($this->directory.'/no/such/dir/app.sqlite', new ProcessRunner());

        foreach ([null, new BackupCipher(BackupKey::generate())] as $cipher) {
            try {
                new Backups($failing, $this->backups, $cipher, $this->media)->create(new DateTimeImmutable());
                self::fail('sqlite3 cannot open the database.');
            } catch (BackupFailed $backupFailed) {
                self::assertStringStartsWith('sqlite3 failed: ', $backupFailed->getMessage());
            }

            self::assertSame([], Files::glob($this->directory.'/backup/*'));
            self::assertSame([], Files::glob($this->directory.'/backup/.phil-backup-*'));
        }
    }

    public function testAFailedMediaArchiveRemovesTheDatabaseFileToo(): void
    {
        if (0 === posix_geteuid()) {
            self::markTestSkipped('root reads every file.');
        }

        chmod($this->directory.'/uploads/poster.jpg', 0o000);
        $backups = new Backups($this->client, $this->backups, null, new MediaArchive(['uploads' => $this->directory.'/uploads']));

        try {
            $backups->create(new DateTimeImmutable());
            self::fail('The media directory cannot be read.');
        } catch (BackupFailed $backupFailed) {
            self::assertStringStartsWith('The media file ', $backupFailed->getMessage());
        }

        self::assertSame([], Files::glob($this->directory.'/backup/*'));
    }

    public function testTheRotationRemovesExpiredSets(): void
    {
        $backups = new Backups($this->client, $this->backups, null, $this->media, new RotationPolicy(last: 2));

        foreach (['2026-11-05', '2026-11-06', '2026-11-07'] as $day) {
            $backups->create(new DateTimeImmutable($day.' 03:00 UTC'));
        }

        $backups->create(new DateTimeImmutable('2026-11-04 03:00 UTC'), 'kept');

        self::assertSame(['app-20261105-030000'], array_map(static fn (BackupSet $set): string => $set->stem, $backups->prune()));
        self::assertCount(6, Files::glob($this->directory.'/backup/*'));
        self::assertSame([], $backups->prune());
        self::assertSame([], new Backups($this->client, $this->backups)->prune(), 'Without a rotation, every set is kept.');
        self::assertCount(6, Files::glob($this->directory.'/backup/*'));
    }

    public function testAnEncryptedSetNeedsTheKey(): void
    {
        $set = new Backups($this->client, $this->backups, new BackupCipher(BackupKey::generate()))->create(new DateTimeImmutable());

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The backup %s is encrypted, and no backup key is given.', $set->database));
        new Backups($this->client, $this->backups)->restore($set);
    }

    public function testWithAKeyASetInClearIsRefused(): void
    {
        $cipher = new BackupCipher(BackupKey::generate());
        $backups = new Backups($this->client, $this->backups, $cipher, $this->media);
        $genuine = $backups->create(new DateTimeImmutable('2026-11-07 03:15:00 UTC'));
        $planted = new Backups($this->client, $this->backups, null, $this->media)->create(new DateTimeImmutable('2099-12-31 00:00:00 UTC'));

        self::assertSame($genuine->stem, $backups->find(null)?->stem, 'A set in clear is never the latest one when there is a key.');

        foreach ([
            static fn (): mixed => $backups->find($planted->database),
            static fn (): mixed => $backups->restore($planted),
            static fn (): mixed => $backups->verify($planted),
            static fn (): mixed => $backups->restore(new BackupSet('x', new DateTimeImmutable(), '', $genuine->database, $planted->media)),
            static fn (): mixed => $backups->verify(new BackupSet('x', new DateTimeImmutable(), '', $genuine->database, $planted->media)),
        ] as $i => $call) {
            try {
                $call();
                self::fail(\sprintf('Call %d reads a file in clear.', $i));
            } catch (BackupFailed $backupFailed) {
                self::assertStringEndsWith('is in clear, and a backup key is given: sets in clear are refused unless they are allowed.', $backupFailed->getMessage());
            }
        }

        $this->sql("UPDATE member SET name = 'changed';");
        self::assertSame("changed\nchanged\n", $this->sql('SELECT name FROM member ORDER BY id;'), 'Nothing was restored.');

        $allowing = new Backups($this->client, $this->backups, $cipher, $this->media, null, true);
        self::assertSame($planted->stem, $allowing->find(null)?->stem, 'Allowed, a set in clear is read as before.');
        self::assertSame($planted->database, $allowing->find($planted->database)?->database);
        self::assertEquals(new Restoration(1, 0), $allowing->restore($planted));
        self::assertSame("Ada\nBob\n", $this->sql('SELECT name FROM member ORDER BY id;'));
        self::assertSame(1, $allowing->verify($planted)->mediaFiles);
        self::assertSame($planted->stem, new Backups($this->client, $this->backups)->find(null)?->stem, 'Without a key, a set in clear is read.');
    }

    public function testADamagedMediaArchiveStopsTheRestoreBeforeTheDatabase(): void
    {
        $backups = new Backups($this->client, $this->backups, null, $this->media);
        $set = $backups->create(new DateTimeImmutable());
        file_put_contents((string) $set->media, substr((string) file_get_contents((string) $set->media), 0, 600));
        $this->sql("UPDATE member SET name = 'changed';");

        try {
            $backups->restore($set);
            self::fail('The archive is cut short.');
        } catch (BackupFailed $backupFailed) {
            self::assertStringEndsWith('is cut short.', $backupFailed->getMessage());
        }

        self::assertSame("changed\nchanged\n", $this->sql('SELECT name FROM member ORDER BY id;'), 'The database is left as it was.');
    }

    public function testASqliteSetIsTestedOnACopyOfItsOwn(): void
    {
        $backups = new Backups($this->client, $this->backups, new BackupCipher(BackupKey::generate()), $this->media);
        $set = $backups->create(new DateTimeImmutable());
        $this->sql("UPDATE member SET name = 'changed';");

        $verification = $backups->verify($set);

        self::assertNotNull($verification->restoredInto);
        self::assertSame('sqlite', $verification->restoredInto->platform);
        self::assertStringStartsWith($this->directory.'/backup/.phil-backup-', $verification->restoredInto->name);
        self::assertFileDoesNotExist($verification->restoredInto->name, 'The copy is removed.');
        self::assertSame(1, $verification->mediaFiles);
        self::assertSame("changed\nchanged\n", $this->sql('SELECT name FROM member ORDER BY id;'), 'The database is not touched.');
        self::assertSame(array_reverse($set->files()), Files::glob($this->directory.'/backup/*'));
        self::assertSame([], Files::glob($this->directory.'/backup/.phil-backup-*'));

        file_put_contents($set->database, 'SQLite format 3 but not really');
        $this->expectException(BackupFailed::class);
        new Backups($this->client, $this->backups)->verify(new BackupSet('x', new DateTimeImmutable(), '', $set->database, null));
    }

    public function testAServerSetIsTestedInTheScratchDatabase(): void
    {
        $runner = new RecordingRunner();
        $client = new MysqlClient('shop', [], $runner, false);
        $backups = new Backups($client, $this->backups);
        file_put_contents($this->directory.'/shop.sql', '-- dump');
        $set = $backups->find($this->directory.'/shop.sql') ?? throw new LogicException('no set');

        self::assertEquals(new Verification(null, null), $backups->verify($set));

        $verification = $backups->verify($set, 'shop_restore_test');
        self::assertEquals(new DatabaseIdentity('mysql', null, 'shop_restore_test'), $verification->restoredInto);
        self::assertSame(
            [['/usr/bin/mysql', 'shop_restore_test', $this->directory.'/shop.sql']],
            array_map(static fn (array $run): array => [$run['command'][0], $run['command'][3] ?? null, $run['input']], $runner->runs),
            'One restore, into the scratch database: without one, nothing was restored.',
        );

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains('The restore test would replace the mysql database shop itself: give it a scratch database of its own.');
        $backups->verify($set, 'shop');
    }

    private function sql(string $sql): string
    {
        return new ProcessRunner()->run(['sqlite3', $this->database, $sql]);
    }
}
