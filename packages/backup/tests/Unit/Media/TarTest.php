<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Media;

use Closure;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Media\TarEntry;
use Phil\Backup\Media\TarReader;
use Phil\Backup\Media\TarWriter;
use Phil\Backup\Tests\Unit\Fixture\Files;
use Phil\Backup\Tests\Unit\Fixture\LyingFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The ustar archives: what is written is read back, and what the reader does not expect is refused.
 */
#[CoversClass(TarWriter::class)]
#[CoversClass(TarReader::class)]
#[CoversClass(TarEntry::class)]
final class TarTest extends TestCase
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

    public function testEntriesAreReadBackAsWritten(): void
    {
        $long = str_repeat('d', 120).'/'.str_repeat('f', 90).'.jpg';
        file_put_contents($this->directory.'/a', 'first');
        file_put_contents($this->directory.'/b', str_repeat('x', 512));
        file_put_contents($this->directory.'/c', '');

        $writer = TarWriter::create($this->directory.'/archive.tar');
        $writer->addDirectory('uploads/', 0o755, 1_700_000_000);
        $writer->addFile('uploads/a.txt', $this->directory.'/a', 0o100644, 1_700_000_001);
        $writer->addFile('uploads/b.bin', $this->directory.'/b', 0o640, 1_700_000_002);
        $writer->addFile($long, $this->directory.'/c', 0o600, 1_700_000_003);
        $writer->addDirectory(str_repeat('e', 150).'/'.str_repeat('g', 98), 0o700, 1_700_000_004);
        $writer->close();

        self::assertSame(0o600, fileperms($this->directory.'/archive.tar') & 0o777);
        self::assertSame(512 * (1 + 2 + 2 + 1 + 1 + 2), filesize($this->directory.'/archive.tar'), 'Headers, content padded to 512 bytes, two end blocks.');

        $entries = [];
        $contents = [];
        new TarReader($this->directory.'/archive.tar')->read(function (TarEntry $entry, Closure $copy) use (&$entries, &$contents): void {
            $entries[] = [$entry->name, $entry->directory, $entry->mode, $entry->size, $entry->modified];

            if (!$entry->directory && 'uploads/b.bin' !== $entry->name) {
                $copy($this->directory.'/out');
                $contents[$entry->name] = file_get_contents($this->directory.'/out');
            }
        });

        self::assertSame([
            ['uploads/', true, 0o755, 0, 1_700_000_000],
            ['uploads/a.txt', false, 0o644, 5, 1_700_000_001],
            ['uploads/b.bin', false, 0o640, 512, 1_700_000_002],
            [$long, false, 0o600, 0, 1_700_000_003],
            [str_repeat('e', 150).'/'.str_repeat('g', 98).'/', true, 0o700, 0, 1_700_000_004],
        ], $entries);
        self::assertSame(['uploads/a.txt' => 'first', $long => ''], $contents, 'Content left unread is skipped.');

        $header = (string) file_get_contents($this->directory.'/archive.tar', length: 512);
        self::assertSame("ustar\x0000", substr($header, 257, 8));
        self::assertSame("0000755\0", substr($header, 100, 8));
        self::assertSame('5', $header[156]);
    }

    public function testEveryFieldIsReadAtItsPlace(): void
    {
        $hundred = str_repeat('h', 100);
        $prefix = str_repeat('p', 155);
        file_put_contents($this->directory.'/a', str_repeat('a', 700));
        file_put_contents($this->directory.'/b', 'bbbbb');

        $writer = TarWriter::create($this->directory.'/archive.tar');
        $writer->addFile('a', $this->directory.'/a', 0o1755, 1_700_000_000);
        $writer->addFile($hundred, $this->directory.'/b', 0o751, 1_700_000_001);
        $writer->addDirectory($prefix.'/d', 0o700, 1_700_000_002);
        $writer->close();

        $archive = (string) file_get_contents($this->directory.'/archive.tar');

        self::assertSame([['a', false, 0o755, 700, 1_700_000_000, 'aaaa'], [$hundred, false, 0o751, 5, 1_700_000_001, 'bbbb'], [$prefix.'/d/', true, 0o700, 0, 1_700_000_002, null]], $this->entries($archive), 'A file of 700 bytes is padded to the next block; the sticky bit is dropped.');

        foreach ([
            'a checksum of eight digits' => [148, 8, null],
            'a mode of eight digits' => [100, 8, '00001755'],
            'a size of twelve digits' => [124, 12, '000000001274'],
            'a time of twelve digits' => [136, 12, '014524770400'],
        ] as $case => [$offset, $length, $field]) {
            $altered = null === $field ? $this->resum($archive, '%08o') : $this->resum(substr_replace($archive, $field, $offset, $length));
            $entries = $this->entries($altered);
            self::assertSame(['a', 700, 1_700_000_000], [$entries[0][0], $entries[0][3], $entries[0][4]], $case);
            self::assertSame(100 === $offset ? 0o1755 : 0o755, $entries[0][2], $case);
        }

        $padded = $this->entries($this->resum(substr_replace($archive, 'x', 512 * 5 + 500, 1), at: 512 * 5));
        self::assertSame($prefix.'/d/', $padded[2][0], 'The padding after the prefix is not part of it.');

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains('is damaged.');
        $this->entries($this->resum(substr_replace($archive, "000644\n\0", 100, 8)));
    }

    public function testAPathWithALineFeedIsSplitWhole(): void
    {
        $writer = TarWriter::create($this->directory.'/archive.tar');
        $writer->addDirectory(str_repeat('p', 10)."\n/".str_repeat('n', 95), 0o755, 0);
        $writer->close();

        self::assertSame([[str_repeat('p', 10)."\n/".str_repeat('n', 95).'/', true, 0o755, 0, 0, null]], $this->entries((string) file_get_contents($this->directory.'/archive.tar')));

        $writer = TarWriter::create($this->directory.'/other.tar');
        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains('is too long for a tar archive.');
        $writer->addFile(str_repeat('p', 10).'/'.str_repeat('n', 100)."\n", $this->directory.'/other.tar', 0o644, 0);
    }

    public function testAPathThatCannotBeSplitIsRefused(): void
    {
        $writer = TarWriter::create($this->directory.'/archive.tar');

        foreach ([str_repeat('n', 101), str_repeat('p', 156).'/name', 'dir/'.str_repeat('n', 101)] as $path) {
            try {
                $writer->addDirectory($path, 0o755, 0);
                self::fail($path);
            } catch (BackupFailed $backupFailed) {
                self::assertSame(\sprintf('The media path %s/ is too long for a tar archive.', $path), $backupFailed->getMessage());
            }
        }

        $writer->addDirectory(str_repeat('p', 155).'/'.str_repeat('n', 99), 0o755, 0);
        $writer->close();
        self::assertSame(1536, filesize($this->directory.'/archive.tar'));
    }

    public function testAFileThatChangesOrIsTooLargeIsRefused(): void
    {
        LyingFile::register();
        $writer = TarWriter::create($this->directory.'/archive.tar');

        foreach ([
            'lying://10/short' => 'The media file lying://10/short changed while it was archived.',
            'lying://8589934592/x' => 'The media file lying://8589934592/x is too large for a tar archive: 8 GiB or more.',
            $this->directory.'/missing' => 'The media file '.$this->directory.'/missing cannot be read.',
        ] as $source => $message) {
            try {
                $writer->addFile('a', $source, 0o644, 0);
                self::fail($message);
            } catch (BackupFailed $backupFailed) {
                self::assertSame($message, $backupFailed->getMessage());
            }
        }

        $writer = TarWriter::create($this->directory.'/largest.tar');

        try {
            $writer->addFile('largest', 'lying://8589934591/', 0o644, 0);
            self::fail('The largest size the header holds is written, then the content is missing.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('The media file lying://8589934591/ changed while it was archived.', $backupFailed->getMessage());
        }

        self::assertSame("77777777777\0", substr((string) file_get_contents($this->directory.'/largest.tar'), 124, 12));
    }

    public function testAnArchiveThatCannotBeWrittenOrReadIsNamed(): void
    {
        try {
            TarWriter::create($this->directory.'/no/archive.tar');
            self::fail('No directory.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame(\sprintf('The archive %s/no/archive.tar cannot be written.', $this->directory), $backupFailed->getMessage());
        }

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The archive %s/missing.tar cannot be read.', $this->directory));
        new TarReader($this->directory.'/missing.tar')->read(static function (): void {});
    }

    public function testADamagedArchiveIsRefused(): void
    {
        file_put_contents($this->directory.'/a', str_repeat('y', 700));
        $writer = TarWriter::create($this->directory.'/archive.tar');
        $writer->addFile('uploads/a', $this->directory.'/a', 0o644, 0);
        $writer->close();

        $archive = (string) file_get_contents($this->directory.'/archive.tar');
        $damaged = 'The archive '.$this->directory.'/altered.tar is damaged.';
        $short = 'The archive '.$this->directory.'/altered.tar is cut short.';

        foreach ([
            'a flipped name' => [substr_replace($archive, 'X', 0, 1), $damaged],
            'another magic' => [substr_replace($archive, 'gnutar', 257, 6), $damaged],
            'a size that is not octal' => [$this->resum(substr_replace($archive, '0000000009', 124, 10)), $damaged],
            'an empty checksum' => [substr_replace($archive, str_repeat(' ', 8), 148, 8), $damaged],
            'a symbolic link' => [$this->resum(substr_replace($archive, '2', 156, 1)), 'The archive '.$this->directory.'/altered.tar holds uploads/a, which is neither a directory nor a regular file.'],
            'a device with a prefix' => [$this->resum(substr_replace(substr_replace($archive, '3', 156, 1), 'dev', 345, 3)), 'The archive '.$this->directory.'/altered.tar holds dev/uploads/a, which is neither a directory nor a regular file.'],
            'no end block' => [substr($archive, 0, 512 * 3), $short],
            'a cut content' => [substr($archive, 0, 512 + 100), $short],
            'a cut header' => [substr($archive, 0, 300), $short],
            'nothing' => ['', $short],
        ] as $case => [$content, $message]) {
            file_put_contents($this->directory.'/altered.tar', $content);

            try {
                new TarReader($this->directory.'/altered.tar')->read(function (TarEntry $entry, Closure $copy): void {
                    $copy($this->directory.'/out');
                });
                self::fail($case);
            } catch (BackupFailed $backupFailed) {
                self::assertSame($message, $backupFailed->getMessage(), $case);
            }
        }

        file_put_contents($this->directory.'/altered.tar', $this->resum(substr_replace($archive, "\0", 156, 1)));
        $types = [];
        new TarReader($this->directory.'/altered.tar')->read(static function (TarEntry $entry) use (&$types): void {
            $types[] = $entry->directory;
        });
        self::assertSame([false], $types, 'A NUL type is a regular file, as in old archives.');
    }

    public function testACopyThatCannotBeWrittenIsNamed(): void
    {
        file_put_contents($this->directory.'/a', 'a');
        $writer = TarWriter::create($this->directory.'/archive.tar');
        $writer->addFile('a', $this->directory.'/a', 0o644, 0);
        $writer->close();

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The media file %s/no/a cannot be written.', $this->directory));
        new TarReader($this->directory.'/archive.tar')->read(function (TarEntry $entry, Closure $copy): void {
            $copy($this->directory.'/no/a');
        });
    }

    /**
     * The archive with the checksum of the header at the offset computed again, written in the format given.
     */
    private function resum(string $archive, string $format = "%06o\0 ", int $at = 0): string
    {
        $header = substr_replace(substr($archive, $at, 512), '        ', 148, 8);

        return substr_replace($archive, \sprintf($format, array_sum(array_map(\ord(...), str_split($header)))), $at + 148, 8);
    }

    /**
     * The entries of the archive, with the first four bytes of each file.
     *
     * @return list<array{string, bool, int, int, int, ?string}>
     */
    private function entries(string $archive): array
    {
        file_put_contents($this->directory.'/read.tar', $archive);
        $entries = [];
        new TarReader($this->directory.'/read.tar')->read(function (TarEntry $entry, Closure $copy) use (&$entries): void {
            $content = null;

            if (!$entry->directory) {
                $copy($this->directory.'/out');
                $content = substr((string) file_get_contents($this->directory.'/out'), 0, 4);
            }

            $entries[] = [$entry->name, $entry->directory, $entry->mode, $entry->size, $entry->modified, $content];
        });

        return $entries;
    }
}
