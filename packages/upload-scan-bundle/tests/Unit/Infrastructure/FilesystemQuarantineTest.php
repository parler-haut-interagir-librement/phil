<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\Exception\QuarantineFailed;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use Phil\UploadScanBundle\Tests\Unit\Fixture\WatchedStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(FilesystemQuarantine::class)]
#[CoversClass(QuarantineFailed::class)]
final class FilesystemQuarantineTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = Temporary::directory();
    }

    protected function tearDown(): void
    {
        Temporary::remove($this->root);
    }

    public function testTheFileIsKeptBesideItsRecordReadableByItsOwnerOnly(): void
    {
        $directory = $this->root.'/var/quarantine';
        $clock = new class implements ClockInterface {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-11-08T10:30:00+00:00');
            }
        };

        $id = new FilesystemQuarantine($directory, $clock)->isolate($this->stream('infected bytes'), "../../etc/\x00passwd\n.png", ScanResult::infected('Eicar-Test-Signature'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $id);
        self::assertSame(['.', '..', $id.'.json', $id.'.quarantine'], scandir($directory));
        self::assertSame(0o700, fileperms($directory) & 0o777);
        self::assertSame(0o600, fileperms($directory.'/'.$id.'.quarantine') & 0o777);
        self::assertSame('infected bytes', file_get_contents($directory.'/'.$id.'.quarantine'));
        self::assertSame(<<<'JSON'
            {
                "name": "../../etc/passwd.png",
                "signature": "Eicar-Test-Signature",
                "quarantined_at": "2026-11-08T10:30:00+00:00"
            }

            JSON, file_get_contents($directory.'/'.$id.'.json'));
    }

    public function testBothFilesArePrivateWhileTheyAreWrittenAndAppearWhole(): void
    {
        $seen = [];
        $id = new FilesystemQuarantine($this->root)->isolate(WatchedStream::open(function () use (&$seen): void {
            foreach ($this->entries() as $entry) {
                $seen[$entry] = fileperms($this->root.'/'.$entry) & 0o777;
            }
        }), 'a.png', ScanResult::infected('X'));

        self::assertCount(1, $seen, 'Only the temporary file of the content exists while it is copied.');
        $temporary = array_key_first($seen);
        self::assertIsString($temporary);
        self::assertMatchesRegularExpression('/^\.phil-quarantine-/', $temporary);

        if ('Windows' !== \PHP_OS_FAMILY) {
            self::assertSame([0o600], array_values($seen));
            self::assertSame(0o600, fileperms($this->root.'/'.$id.'.json') & 0o777);
            self::assertSame(0o600, fileperms($this->root.'/'.$id.'.quarantine') & 0o777);
        }

        self::assertSame([$id.'.json', $id.'.quarantine'], $this->entries());
        self::assertSame('watched bytes', file_get_contents($this->root.'/'.$id.'.quarantine'));
    }

    public function testTwoFilesKeptAtTheSameMomentNeverMeet(): void
    {
        $quarantine = new FilesystemQuarantine($this->root);
        $inner = null;
        $outer = $quarantine->isolate(WatchedStream::open(function () use ($quarantine, &$inner): void {
            $inner = $quarantine->isolate($this->stream('inner bytes'), 'inner.png', ScanResult::infected('Inner'));
        }), 'outer.png', ScanResult::infected('Outer'));

        self::assertIsString($inner);
        self::assertSame('watched bytes', file_get_contents($this->root.'/'.$outer.'.quarantine'));
        self::assertSame('inner bytes', file_get_contents($this->root.'/'.$inner.'.quarantine'));
        self::assertSame('Outer', $this->record($outer)['signature']);
        self::assertSame('Inner', $this->record($inner)['signature']);
        self::assertCount(4, $this->entries());
    }

    public function testAnOpenDirectoryIsNarrowedBeforeAFileIsKept(): void
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            self::markTestSkipped("Windows has no group or other users' bits.");
        }

        foreach ([0o755, 0o750, 0o705, 0o710, 0o701] as $mode) {
            chmod($this->root, $mode);
            new FilesystemQuarantine($this->root)->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
            clearstatcache();

            self::assertSame(0o700, fileperms($this->root) & 0o777);
        }
    }

    public function testAnOpenDirectoryIsRefusedWhenItMayNotOrCannotBeNarrowed(): void
    {
        if ('Windows' === \PHP_OS_FAMILY) {
            self::markTestSkipped("Windows has no group or other users' bits.");
        }

        chmod($this->root, 0o751);

        try {
            new FilesystemQuarantine($this->root, null, false)->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
            self::fail('The directory is open.');
        } catch (QuarantineFailed $quarantineFailed) {
            self::assertSame(\sprintf('The quarantine directory %s is open to other users (mode 0751): narrow it to its owner, with mode 0700.', $this->root), $quarantineFailed->getMessage());
        }

        clearstatcache();
        self::assertSame(0o751, fileperms($this->root) & 0o777);
        self::assertSame([], $this->entries());
        chmod($this->root, 0o700);
        $id = new FilesystemQuarantine($this->root, null, false)->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
        self::assertSame([$id.'.json', $id.'.quarantine'], $this->entries(), 'A private directory is used as it is.');
        $fresh = new FilesystemQuarantine($this->root.'/fresh/quarantine', null, false)->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
        self::assertFileExists($this->root.'/fresh/quarantine/'.$fresh.'.json', 'A directory it creates is private.');

        // A directory the tests created belongs to the user they run as.
        if (0 === fileowner($this->root)) {
            self::markTestSkipped('root narrows every directory.');
        }

        // The system's shared temporary directory belongs to root and is open to everyone.
        $this->expectException(QuarantineFailed::class);
        $this->expectExceptionMessageIsOrContains('The quarantine directory /tmp is open to other users (mode 0777) and cannot be narrowed to its owner: give it to the user that runs the application, with mode 0700.');
        $this->expectOutputString('');
        new FilesystemQuarantine('/tmp')->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
    }

    public function testADirectoryReachedThroughASymbolicLinkKeepsTheFiles(): void
    {
        mkdir($this->root.'/real', 0o700);
        symlink($this->root.'/real', $this->root.'/link');
        $id = new FilesystemQuarantine($this->root.'/link')->isolate($this->stream('x'), 'x', ScanResult::infected('X'));

        self::assertFileExists($this->root.'/real/'.$id.'.quarantine');
        self::assertFileExists($this->root.'/real/'.$id.'.json');
    }

    public function testEachFileHasItsOwnIdentifier(): void
    {
        $quarantine = new FilesystemQuarantine($this->root);

        self::assertNotSame($quarantine->isolate($this->stream('a'), 'a', ScanResult::infected('A')), $quarantine->isolate($this->stream('b'), 'b', ScanResult::infected('B')));
    }

    public function testWithoutAClockTheRecordIsDatedNowAndTheNameIsCut(): void
    {
        $before = new DateTimeImmutable('-1 second');
        $record = $this->record(new FilesystemQuarantine($this->root)->isolate($this->stream('x'), str_repeat('n', 300), ScanResult::infected('X')));

        self::assertSame(str_repeat('n', 255), $record['name']);
        self::assertIsString($record['quarantined_at']);
        self::assertGreaterThanOrEqual($before, new DateTimeImmutable($record['quarantined_at']));
    }

    public function testANameThatIsNotUtf8IsKeptReadable(): void
    {
        $record = $this->record(new FilesystemQuarantine($this->root)->isolate($this->stream('x'), "ok\xFF", ScanResult::infected('X')));

        self::assertSame("ok\u{FFFD}", $record['name']);
    }

    public function testADirectoryThatCannotBeCreatedFails(): void
    {
        file_put_contents($this->root.'/file', 'x');

        $this->expectException(QuarantineFailed::class);
        $this->expectExceptionMessageIsOrContains('The quarantine directory '.$this->root.'/file/quarantine cannot be created.');
        $this->expectOutputString('');

        new FilesystemQuarantine($this->root.'/file/quarantine')->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
    }

    public function testTheWarningOfADirectoryThatCannotBeCreatedIsSilenced(): void
    {
        file_put_contents($this->root.'/file', 'x');
        error_clear_last();

        try {
            new FilesystemQuarantine($this->root.'/file/quarantine')->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
            self::fail('The quarantine was created under a file.');
        } catch (QuarantineFailed) {
            self::assertNull(error_get_last());
        }
    }

    public function testADirectoryThatRefusesFilesFails(): void
    {
        chmod($this->root, 0o500);

        if (is_writable($this->root)) {
            self::markTestSkipped('The tests run as a user every directory is writable by.');
        }

        $fallback = (string) realpath(sys_get_temp_dir());
        $before = glob($fallback.'/.phil-quarantine-*');

        try {
            new FilesystemQuarantine($this->root)->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
            self::fail('The directory refuses files.');
        } catch (QuarantineFailed $quarantineFailed) {
            self::assertSame('The quarantine directory '.$this->root.' does not accept files.', $quarantineFailed->getMessage());
        }

        self::assertSame($before, glob($fallback.'/.phil-quarantine-*'), 'The file tempnam() falls back to elsewhere is removed.');
    }

    public function testAStreamThatCannotBeCopiedFails(): void
    {
        $this->expectException(QuarantineFailed::class);
        $this->expectExceptionMessageMatches('/^The file [0-9a-f]{32} could not be written whole to the quarantine\.$/D');

        $stream = $this->stream('x');
        fclose($stream);

        try {
            new FilesystemQuarantine($this->root)->isolate($stream, 'x', ScanResult::infected('X'));
        } finally {
            self::assertSame([], $this->entries(), 'Neither the file nor its record, nor a temporary file, is left.');
        }
    }

    public function testAnUnreadableStreamFails(): void
    {
        $this->expectException(QuarantineFailed::class);

        $directory = opendir($this->root);
        self::assertIsResource($directory);

        new FilesystemQuarantine($this->root)->isolate($directory, 'x', ScanResult::infected('X'));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function record(string $id): array
    {
        $record = json_decode((string) file_get_contents($this->root.'/'.$id.'.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);

        return $record;
    }

    /**
     * @return list<string>
     */
    private function entries(): array
    {
        $entries = scandir($this->root);

        return array_values(array_diff(false === $entries ? [] : $entries, ['.', '..']));
    }

    /**
     * @return resource
     */
    private function stream(string $contents): mixed
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }
}
