<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\Exception\QuarantineFailed;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
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

        $this->expectException(QuarantineFailed::class);
        $this->expectExceptionMessageIsOrContains('The quarantine directory '.$this->root.' does not accept files.');

        new FilesystemQuarantine($this->root)->isolate($this->stream('x'), 'x', ScanResult::infected('X'));
    }

    public function testAStreamThatCannotBeCopiedFails(): void
    {
        $this->expectException(QuarantineFailed::class);
        $this->expectExceptionMessageMatches('/^The file [0-9a-f]{32} could not be written whole to the quarantine\.$/D');

        $stream = $this->stream('x');
        fclose($stream);

        new FilesystemQuarantine($this->root)->isolate($stream, 'x', ScanResult::infected('X'));
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
