<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Storage;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Storage\BackupDirectory;
use Phil\Backup\Storage\BackupSet;
use Phil\Backup\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Sets named, found, read from their names and removed.
 */
#[CoversClass(BackupDirectory::class)]
#[CoversClass(BackupSet::class)]
final class BackupDirectoryTest extends TestCase
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

    public function testAStemNamesTheDatabaseTheTimeInUtcAndTheLabel(): void
    {
        $backups = new BackupDirectory($this->directory.'/var/backup');
        $at = new DateTimeImmutable('2026-11-06 23:15:00', new DateTimeZone('America/Toronto'));

        self::assertSame($this->directory.'/var/backup', $backups->path());
        self::assertSame('app-20261107-041500', $backups->newStem('app', $at));
        self::assertSame(0o700, fileperms($this->directory.'/var/backup') & 0o777);
        self::assertSame('app-20261107-041500-before-restore', $backups->newStem('/data/app.sqlite', $at, 'before-restore'));
        self::assertSame('my-shop-20261107-041500-2', $backups->newStem('my shop', $at, '2'));
        self::assertSame($this->directory.'/var/backup/a.sql', $backups->file('a.sql'));

        foreach (['Before', '-x', 'a_b', 'a b', "a\n"] as $label) {
            try {
                $backups->newStem('app', $at, $label);
                self::fail($label);
            } catch (InvalidBackupValue $invalidBackupValue) {
                self::assertSame(\sprintf('The label "%s" holds characters other than lowercase letters, digits and "-", or starts with "-".', $label), $invalidBackupValue->getMessage());
            }
        }
    }

    public function testAStemAlreadyTakenGetsACopyNumber(): void
    {
        $backups = new BackupDirectory($this->directory);
        $at = new DateTimeImmutable('2026-11-07 04:10:00 UTC');

        touch($this->directory.'/app-20261107-041000-before-restore.media.tar.enc');
        self::assertSame('app-20261107-041000-before-restore_2', $backups->newStem('app', $at, 'before-restore'), 'Any file of the stem takes it.');
        touch($this->directory.'/app-20261107-041000-before-restore_2.sql');
        self::assertSame('app-20261107-041000-before-restore_3', $backups->newStem('app', $at, 'before-restore'));
        self::assertSame('app-20261107-041000', $backups->newStem('app', $at), 'Another label is another stem.');
        touch($this->directory.'/app-20261107-041000.sql');
        touch($this->directory.'/app-20261107-041000-before-restore.sql');

        foreach (range(3, BackupDirectory::COPIES - 1) as $copy) {
            touch(\sprintf('%s/app-20261107-041000_%d.sql', $this->directory, $copy));
        }

        self::assertSame('app-20261107-041000_2', $backups->newStem('app', $at));
        touch($this->directory.'/app-20261107-041000_2.sql');
        self::assertSame(\sprintf('app-20261107-041000_%d', BackupDirectory::COPIES), $backups->newStem('app', $at), 'The last copy number is taken too.');
        touch(\sprintf('%s/app-20261107-041000_%d.sql', $this->directory, BackupDirectory::COPIES));

        $sets = $backups->sets('sql');
        self::assertCount(BackupDirectory::COPIES + 2, $sets);
        self::assertSame(['app-20261107-041000', 'app-20261107-041000-before-restore', 'app-20261107-041000-before-restore_2', 'app-20261107-041000_2', 'app-20261107-041000_3'], array_map(static fn (BackupSet $set): string => $set->stem, \array_slice($sets, 0, 5)), 'By time, then copy number.');
        self::assertSame([1, 1, 2, 2, 3], array_map(static fn (BackupSet $set): int => $set->copy, \array_slice($sets, 0, 5)));
        self::assertSame(1, new BackupSet('app-20261107-041000', $at, '', 'app.sql', null)->copy, 'A set without a copy number is the first of its stem.');
        self::assertSame(['', 'before-restore', 'before-restore', ''], array_map(static fn (BackupSet $set): string => $set->label, \array_slice($sets, 0, 4)));
        self::assertSame(\sprintf('app-20261107-041000_%d', BackupDirectory::COPIES), $backups->latest('sql')->stem ?? null);
        self::assertSame(['app-20261107-041000-before-restore', 'app-20261107-041000-before-restore_2'], array_map(static fn (BackupSet $set): string => $set->stem, $backups->labelled('sql', 'before-restore')));
        self::assertSame(100, BackupDirectory::COPIES);

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The backup directory %s already holds 100 backups named app-20261107-041000.', $this->directory));
        $backups->newStem('app', $at);
    }

    public function testACopyNumberIsTwoOrMore(): void
    {
        $backups = new BackupDirectory($this->directory);

        foreach (['app-20261107-041000_1.sql', 'app-20261107-041000_02.sql', 'app-20261107-041000_.sql', 'app-20261107-041000_10.sql', 'app-20261107-041000-x_9.sql'] as $file) {
            touch($this->directory.'/'.$file);
        }

        self::assertSame([['app-20261107-041000-x_9', 'x', 9], ['app-20261107-041000_10', '', 10]], array_map(static fn (BackupSet $set): array => [$set->stem, $set->label, $set->copy], $backups->sets('sql')));
    }

    public function testATemporaryFileIsTheDirectorysOwn(): void
    {
        $backups = new BackupDirectory($this->directory.'/backup');
        $file = $backups->temporary();

        self::assertSame($this->directory.'/backup', \dirname($file));
        self::assertStringStartsWith('.phil-backup-', basename($file));
        self::assertSame(0o600, fileperms($file) & 0o777);
        self::assertSame(0, filesize($file));
    }

    public function testADirectoryThatCannotBeUsedIsNamed(): void
    {
        file_put_contents($this->directory.'/file', '');

        foreach (['stem' => static fn (BackupDirectory $backups): string => $backups->newStem('app', new DateTimeImmutable()), 'temporary' => static fn (BackupDirectory $backups): string => $backups->temporary()] as $case => $call) {
            try {
                $call(new BackupDirectory($this->directory.'/file/backup'));
                self::fail($case);
            } catch (BackupFailed $backupFailed) {
                self::assertSame(\sprintf('The backup directory %s/file/backup cannot be created.', $this->directory), $backupFailed->getMessage());
            }
        }

        if (0 === posix_geteuid()) {
            self::markTestSkipped('root writes in every directory.');
        }

        mkdir($this->directory.'/locked', 0o500);
        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('No temporary file can be created in the backup directory %s/locked.', $this->directory));
        new BackupDirectory($this->directory.'/locked')->temporary();
    }

    public function testSetsAreReadFromTheirNamesOldestFirst(): void
    {
        $backups = new BackupDirectory($this->directory);

        foreach ([
            'app-20261107-031500.sql', 'app-20261107-031500.media.tar',
            'app-20261105-031500.sql.enc', 'app-20261105-031500.media.tar.enc',
            'app-20261106-031500-before-restore.sql',
            'shop-2-20261106-031500.sql',
            'app-20261108-000000-latest-but-labelled.sql',
            'app-20261332-031500.sql', 'app-20261107-246000.sql', 'notes.sql', 'app-20261107-031500.dump', 'app-20261107-031500.sql.gz',
        ] as $file) {
            touch($this->directory.'/'.$file);
        }

        $sets = $backups->sets('sql');
        self::assertSame(['app-20261105-031500', 'app-20261106-031500-before-restore', 'shop-2-20261106-031500', 'app-20261107-031500', 'app-20261108-000000-latest-but-labelled'], array_map(static fn (BackupSet $set): string => $set->stem, $sets));
        self::assertSame([$this->directory.'/app-20261105-031500.sql.enc', $this->directory.'/app-20261105-031500.media.tar.enc'], $sets[0]->files());
        self::assertSame([$this->directory.'/app-20261106-031500-before-restore.sql'], $sets[1]->files());
        self::assertSame(['', 'before-restore', '', '', 'latest-but-labelled'], array_map(static fn (BackupSet $set): string => $set->label, $sets));
        self::assertSame([false, true, false, false, true], array_map(static fn (BackupSet $set): bool => $set->isLabelled(), $sets));
        self::assertSame('2026-11-06T03:15:00+00:00', $sets[1]->time->format(\DATE_ATOM));
        self::assertTrue(BackupSet::isEncrypted($sets[0]->database));
        self::assertFalse(BackupSet::isEncrypted($sets[3]->database));
        self::assertFalse(BackupSet::isEncrypted('app.sqlenc'));
        self::assertSame($this->directory.'/app-20261107-031500.media.tar', $sets[3]->media);

        self::assertSame('app-20261107-031500', $backups->latest('sql')->stem ?? null);
        unlink($this->directory.'/app-20261107-031500.sql');
        self::assertSame('shop-2-20261106-031500', $backups->latest('sql')->stem ?? null, 'A labelled set is never the latest.');
        self::assertNull($backups->latest('sqlite'));
        self::assertSame([], new BackupDirectory($this->directory.'/none')->sets('sql'));
    }

    public function testAMediaArchiveIsPairedWithADatabaseFileOfTheSameEncryption(): void
    {
        $backups = new BackupDirectory($this->directory);

        foreach (['app-20261107-031500.sql.enc', 'app-20261107-031500.media.tar', 'app-20261108-031500.sql', 'app-20261108-031500.media.tar.enc', 'app-20261109-031500.sql.enc', 'app-20261109-031500.media.tar.enc', 'app-20261109-031500.media.tar', 'app-20261110-031500.sql'] as $file) {
            touch($this->directory.'/'.$file);
        }

        self::assertSame(
            [
                [$this->directory.'/app-20261107-031500.sql.enc'],
                [$this->directory.'/app-20261108-031500.sql'],
                [$this->directory.'/app-20261109-031500.sql.enc', $this->directory.'/app-20261109-031500.media.tar.enc'],
                [$this->directory.'/app-20261110-031500.sql'],
            ],
            array_map(static fn (BackupSet $set): array => $set->files(), $backups->sets('sql')),
        );
        self::assertSame(['app-20261107-031500', 'app-20261109-031500'], array_map(static fn (BackupSet $set): string => $set->stem, $backups->sets('sql', true)));
        self::assertSame(['app-20261108-031500', 'app-20261110-031500'], array_map(static fn (BackupSet $set): string => $set->stem, $backups->sets('sql', false)));
        self::assertSame('app-20261109-031500', $backups->latest('sql', true)->stem ?? null);
        self::assertSame('app-20261110-031500', $backups->latest('sql')->stem ?? null);
        self::assertSame([], $backups->labelled('sql', 'none', true));
    }

    public function testAFileGivenByItsPathIsASetWhateverItsName(): void
    {
        $backups = new BackupDirectory($this->directory.'/backup');
        mkdir($this->directory.'/elsewhere');
        touch($this->directory.'/elsewhere/app-20261107-031500.sql.enc');
        touch($this->directory.'/elsewhere/app-20261107-031500.media.tar.enc');
        touch($this->directory.'/elsewhere/production.sql', 1_700_000_000);

        $named = $backups->set($this->directory.'/elsewhere/app-20261107-031500.sql.enc', 'sql');
        self::assertSame($this->directory.'/elsewhere/app-20261107-031500.media.tar.enc', $named->media);

        self::assertSame('app-20261107-031500.sql.enc', $backups->set($this->directory.'/elsewhere/app-20261107-031500.sql.enc', 's.l')->stem, 'An extension is matched as written.');

        $other = $backups->set($this->directory.'/elsewhere/production.sql', 'sql');
        self::assertSame(['production.sql', 1_700_000_000, '', $this->directory.'/elsewhere/production.sql', null], [$other->stem, $other->time->getTimestamp(), $other->label, $other->database, $other->media]);

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The backup %s/missing.sql does not exist.', $this->directory));
        $backups->set($this->directory.'/missing.sql', 'sql');
    }

    public function testRemovingASetRemovesItsFiles(): void
    {
        $backups = new BackupDirectory($this->directory);
        touch($this->directory.'/app-20261107-031500.sql');
        touch($this->directory.'/app-20261107-031500.media.tar');
        touch($this->directory.'/app-20261108-031500.sql');
        [$first, $second] = $backups->sets('sql');

        unlink($this->directory.'/app-20261107-031500.media.tar');
        $backups->remove($first);
        $backups->remove($second);

        self::assertSame([], Files::glob($this->directory.'/*'));
    }
}
