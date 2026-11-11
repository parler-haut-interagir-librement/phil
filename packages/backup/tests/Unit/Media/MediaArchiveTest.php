<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Media;

use FilesystemIterator;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Media\MediaArchive;
use Phil\Backup\Media\TarEntry;
use Phil\Backup\Media\TarReader;
use Phil\Backup\Media\TarWriter;
use Phil\Backup\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Named media directories archived and written back, elsewhere, without leaving them.
 */
#[CoversClass(MediaArchive::class)]
final class MediaArchiveTest extends TestCase
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

    public function testDirectoriesAreArchivedAndWrittenBackElsewhere(): void
    {
        $uploads = $this->directory.'/u';
        mkdir($uploads.'/2026/11', 0o755, true);
        file_put_contents($uploads.'/2026/11/poster.jpg', 'jpeg');
        file_put_contents($uploads.'/b.txt', 'b');
        chmod($uploads.'/b.txt', 0o640);
        touch($uploads.'/b.txt', 1_700_000_000);
        file_put_contents($this->directory.'/outside.txt', 'outside');
        symlink($this->directory.'/outside.txt', $uploads.'/link.txt');
        symlink($this->directory, $uploads.'/linked-dir');
        $socket = stream_socket_server('unix://'.$uploads.'/s');

        $archive = new MediaArchive(['uploads' => $uploads.'/', 'documents' => $this->directory.'/not-yet']);
        self::assertFalse($archive->isEmpty());
        self::assertTrue(new MediaArchive([])->isEmpty());
        self::assertSame(2, $archive->write($this->directory.'/media.tar'));
        self::assertSame(2, $archive->verify($this->directory.'/media.tar'));

        $names = [];
        new TarReader($this->directory.'/media.tar')->read(static function (TarEntry $entry) use (&$names): void {
            $names[] = $entry->name;
        });
        self::assertSame(['uploads/', 'uploads/2026/', 'uploads/2026/11/', 'uploads/2026/11/poster.jpg', 'uploads/b.txt', 'documents/'], $names, 'Sorted, links and other files left out.');

        $elsewhere = new MediaArchive(['uploads' => $this->directory.'/restored/uploads', 'documents' => $this->directory.'/restored/documents']);
        mkdir($this->directory.'/restored/uploads', 0o755, true);
        file_put_contents($this->directory.'/restored/uploads/kept.txt', 'kept');
        file_put_contents($this->directory.'/restored/uploads/b.txt', 'old');
        self::assertSame(2, $elsewhere->extract($this->directory.'/media.tar'));

        self::assertSame('jpeg', file_get_contents($this->directory.'/restored/uploads/2026/11/poster.jpg'));
        self::assertSame('b', file_get_contents($this->directory.'/restored/uploads/b.txt'));
        self::assertSame(0o640, fileperms($this->directory.'/restored/uploads/b.txt') & 0o777);
        self::assertSame(1_700_000_000, filemtime($this->directory.'/restored/uploads/b.txt'));
        self::assertSame('kept', file_get_contents($this->directory.'/restored/uploads/kept.txt'), 'A file the archive does not hold is left.');
        self::assertDirectoryExists($this->directory.'/restored/documents');
        self::assertNotFalse($socket, 'A socket is neither a directory nor a regular file.');
        fclose($socket);
    }

    public function testPruningRemovesWhatTheArchiveDoesNotHold(): void
    {
        $source = $this->directory.'/source';
        mkdir($source.'/kept/empty', 0o755, true);
        file_put_contents($source.'/kept/a.txt', 'a');
        file_put_contents($source.'/b.txt', 'b');
        new MediaArchive(['uploads' => $source])->write($this->directory.'/media.tar');

        $target = $this->directory.'/target';
        $other = $this->directory.'/other';
        mkdir($target.'/kept', 0o755, true);
        mkdir($target.'/gone/deeper', 0o755, true);
        mkdir($other);
        file_put_contents($target.'/kept/a.txt', 'old');
        file_put_contents($target.'/kept/added.txt', 'added');
        file_put_contents($target.'/gone/deeper/c.txt', 'c');
        file_put_contents($target.'/gone-too.txt', 'gone');
        file_put_contents($other.'/untouched.txt', 'untouched');
        file_put_contents($this->directory.'/outside.txt', 'outside');
        symlink($this->directory.'/outside.txt', $target.'/link.txt');
        symlink($other, $target.'/linked-dir');
        $archive = new MediaArchive(['uploads' => $target, 'documents' => $other]);

        self::assertSame(2, $archive->extract($this->directory.'/media.tar'));
        self::assertSame(5, $archive->prune($this->directory.'/media.tar'));

        $left = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            self::assertInstanceOf(SplFileInfo::class, $entry);
            $left[] = substr($entry->getPathname(), \strlen($target) + 1);
        }

        sort($left);
        self::assertSame(['b.txt', 'kept', 'kept/a.txt', 'kept/empty'], $left, 'Files, links and emptied directories go; an empty directory the archive holds stays.');
        self::assertSame('a', file_get_contents($target.'/kept/a.txt'));
        self::assertSame('outside', file_get_contents($this->directory.'/outside.txt'), 'A link is removed, not followed.');
        self::assertSame('untouched', file_get_contents($other.'/untouched.txt'), 'A directory the archive does not name is left.');
        self::assertSame(0, $archive->prune($this->directory.'/media.tar'));

        file_put_contents($this->directory.'/foreign.tar', '');
        $writer = TarWriter::create($this->directory.'/foreign.tar');
        $writer->addDirectory('unknown', 0o755, 0);
        $writer->close();

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains('The archive holds media of "unknown", which the configuration does not name.');
        $archive->prune($this->directory.'/foreign.tar');
    }

    public function testALinkInTheWayIsReplacedAndALinkedDirectoryIsNotLeft(): void
    {
        $source = $this->directory.'/source';
        mkdir($source.'/sub', 0o755, true);
        file_put_contents($source.'/a.txt', 'a');
        file_put_contents($source.'/sub/b.txt', 'b');
        new MediaArchive(['media' => $source])->write($this->directory.'/media.tar');

        $target = $this->directory.'/target';
        mkdir($target);
        file_put_contents($this->directory.'/secret', 'secret');
        symlink($this->directory.'/secret', $target.'/a.txt');
        mkdir($this->directory.'/elsewhere');
        symlink($this->directory.'/elsewhere', $target.'/sub');

        try {
            new MediaArchive(['media' => $target])->extract($this->directory.'/media.tar');
            self::fail('A linked directory leads out.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('The media path media/sub/ leads out of its directory through a link.', $backupFailed->getMessage());
        }

        self::assertSame('secret', file_get_contents($this->directory.'/secret'));
        self::assertSame('a', file_get_contents($target.'/a.txt'));
        self::assertFalse(is_link($target.'/a.txt'), 'A link in the way of a file is replaced, never written through.');
        self::assertFileDoesNotExist($this->directory.'/elsewhere/b.txt');
    }

    public function testAnArchiveIsVerifiedWholeBeforeAnythingIsWritten(): void
    {
        file_put_contents($this->directory.'/a', 'a');

        foreach ([
            'other/a' => 'The archive holds media of "other", which the configuration does not name.',
            '/etc/passwd' => 'The archive holds media of "", which the configuration does not name.',
            'media/../a' => 'The media path media/../a is not a plain relative path.',
            'media/./a' => 'The media path media/./a is not a plain relative path.',
            'media//a' => 'The media path media//a is not a plain relative path.',
        ] as $path => $message) {
            $writer = TarWriter::create($this->directory.'/media.tar');
            $writer->addFile('media/first', $this->directory.'/a', 0o644, 0);
            $writer->addFile($path, $this->directory.'/a', 0o644, 0);
            $writer->close();

            try {
                new MediaArchive(['media' => $this->directory.'/target'])->extract($this->directory.'/media.tar');
                self::fail($path);
            } catch (BackupFailed $backupFailed) {
                self::assertSame($message, $backupFailed->getMessage());
                self::assertFileDoesNotExist($this->directory.'/target/first', 'Nothing is written before the whole archive is read.');
            }
        }
    }

    public function testADirectoryThatCannotBeCreatedOrReadIsNamed(): void
    {
        $writer = TarWriter::create($this->directory.'/media.tar');
        $writer->addDirectory('media/sub', 0o755, 0);
        $writer->close();
        file_put_contents($this->directory.'/file', '');

        try {
            new MediaArchive(['media' => $this->directory.'/file'])->extract($this->directory.'/media.tar');
            self::fail('A file where a directory goes.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame(\sprintf('The media directory %s/file/sub cannot be created.', $this->directory), $backupFailed->getMessage());
        }

        if (0 === posix_geteuid()) {
            self::markTestSkipped('root reads every directory.');
        }

        mkdir($this->directory.'/locked/inner', 0o755, true);
        chmod($this->directory.'/locked/inner', 0o000);

        try {
            new MediaArchive(['media' => $this->directory.'/locked'])->write($this->directory.'/locked.tar');
            self::fail('An unreadable directory.');
        } catch (BackupFailed $backupFailed) {
            self::assertStringStartsWith(\sprintf('The media directory %s/locked cannot be read whole: ', $this->directory), $backupFailed->getMessage());
        }
    }

    public function testAMediaNameIsAPlainWord(): void
    {
        new MediaArchive(['up_loads-2' => '/a', 'B' => '/b']);

        foreach (['', 'up loads', 'a/b', '../x', "a\n"] as $name) {
            try {
                new MediaArchive([$name => '/x']);
                self::fail($name);
            } catch (InvalidBackupValue $invalidBackupValue) {
                self::assertSame(\sprintf('The media name "%s" holds characters other than letters, digits, "-" and "_".', $name), $invalidBackupValue->getMessage());
            }
        }
    }
}
