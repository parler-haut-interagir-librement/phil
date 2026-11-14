<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Application;

use Phil\UploadScan\Decision;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\Event\FileFlagged;
use Phil\UploadScanBundle\Application\Event\FileInfected;
use Phil\UploadScanBundle\Application\Exception\QuarantineFailed;
use Phil\UploadScanBundle\Application\Inspection;
use Phil\UploadScanBundle\Application\UploadScanner;
use Phil\UploadScanBundle\Tests\Unit\Fixture\CondemningScanner;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingLogger;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingQuarantine;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use Phil\UploadScanBundle\Tests\Unit\Fixture\ThrowingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(UploadScanner::class)]
#[CoversClass(Inspection::class)]
#[CoversClass(FileInfected::class)]
#[CoversClass(FileFlagged::class)]
#[CoversClass(QuarantineFailed::class)]
final class UploadScannerTest extends TestCase
{
    public function testACleanFileIsAcceptedQuietly(): void
    {
        $events = new RecordingDispatcher();
        $quarantine = new RecordingQuarantine();
        $inspection = new UploadScanner(new FakeScanner(), new ScanPolicy(), $quarantine, $events)->inspectContents('a manuscript', 'book.odt');

        self::assertEquals(new Inspection(ScanResult::clean(), Decision::Accept), $inspection);
        self::assertSame([true, false, false], [$inspection->accepted(), $inspection->flagged(), $inspection->infected()]);
        self::assertSame([], $events->events);
        self::assertSame([], $quarantine->kept);
    }

    public function testAnInfectedFileIsQuarantinedAndAnnounced(): void
    {
        $events = new RecordingDispatcher();
        $quarantine = new RecordingQuarantine();
        $inspection = new UploadScanner(new FakeScanner(), new ScanPolicy(OnScanError::AcceptAndFlag), $quarantine, $events)->inspectContents(Temporary::eicar(), 'cover.png', 'media');

        self::assertEquals(new Inspection(ScanResult::infected('Eicar-Test-Signature'), Decision::Reject, 'q1'), $inspection);
        self::assertSame([false, false, true], [$inspection->accepted(), $inspection->flagged(), $inspection->infected()]);
        self::assertEquals([new FileInfected('cover.png', 'Eicar-Test-Signature', 'media', 'q1')], $events->events);
        self::assertEquals(['q1' => [Temporary::eicar(), 'cover.png', ScanResult::infected('Eicar-Test-Signature')]], $quarantine->kept);
    }

    public function testTheFileIsScannedAndQuarantinedWholeWhereverTheStreamStands(): void
    {
        $quarantine = new RecordingQuarantine();
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'header|'.Temporary::eicar());
        fseek($stream, 7);

        $inspection = new UploadScanner(new FakeScanner(), quarantine: $quarantine)->inspectStream($stream, 'sniffed');

        self::assertSame('q1', $inspection->quarantineId);
        self::assertSame('header|'.Temporary::eicar(), $quarantine->kept['q1'][0], 'A stream read before, to sniff its type, is kept whole.');
    }

    public function testAFileFromAStreamThatCannotBeReadAgainIsNotQuarantined(): void
    {
        $events = new RecordingDispatcher();
        $quarantine = new RecordingQuarantine();
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        [$stream, $writer] = $pair;
        fwrite($writer, Temporary::eicar());
        fclose($writer);

        try {
            $inspection = new UploadScanner(new FakeScanner(), new ScanPolicy(), $quarantine, $events)->inspectStream($stream, 'piped');
        } finally {
            fclose($stream);
        }

        self::assertTrue($inspection->infected());
        self::assertNull($inspection->quarantineId);
        self::assertSame([], $quarantine->kept);
        self::assertEquals([new FileInfected('piped', 'Eicar-Test-Signature', 'upload')], $events->events);
    }

    public function testWithoutAQuarantineAnInfectedFileIsOnlyRefused(): void
    {
        $inspection = new UploadScanner(new FakeScanner())->inspectContents(Temporary::eicar(), 'x');

        self::assertSame([Decision::Reject, null], [$inspection->decision, $inspection->quarantineId]);
    }

    public function testAQuarantineThatFailsIsLoggedAndTheFileStillRefused(): void
    {
        $logger = new RecordingLogger();
        $events = new RecordingDispatcher();
        $inspection = new UploadScanner(new FakeScanner(), new ScanPolicy(), new RecordingQuarantine(fails: true), $events, $logger)->inspectContents(Temporary::eicar(), 'x');

        self::assertSame([Decision::Reject, null], [$inspection->decision, $inspection->quarantineId]);
        self::assertSame([['error', 'An infected file could not be quarantined: {reason}', ['reason' => 'The disk is full.', 'signature' => 'Eicar-Test-Signature']]], $logger->records);
        self::assertEquals([new FileInfected('x', 'Eicar-Test-Signature', 'upload')], $events->events);
        self::assertTrue(new UploadScanner(new FakeScanner(), new ScanPolicy(), new RecordingQuarantine(fails: true))->inspectContents(Temporary::eicar(), 'x')->infected());
    }

    public function testAFileThatCouldNotBeScannedIsFlaggedUnderThatPolicy(): void
    {
        $events = new RecordingDispatcher();
        $inspection = new UploadScanner(new FakeScanner(error: 'clamd is down'), new ScanPolicy(OnScanError::AcceptAndFlag), new RecordingQuarantine(), $events)->inspectContents('x', 'book.docx', 'document_import');

        self::assertEquals(new Inspection(ScanResult::error('clamd is down'), Decision::Flag), $inspection);
        self::assertSame([true, true, false], [$inspection->accepted(), $inspection->flagged(), $inspection->infected()]);
        self::assertEquals([new FileFlagged('book.docx', 'clamd is down', 'document_import')], $events->events);
    }

    public function testAFileThatCouldNotBeScannedIsRefusedByDefault(): void
    {
        $events = new RecordingDispatcher();
        $inspection = new UploadScanner(new FakeScanner(error: 'clamd is down'), events: $events)->inspectContents('x', 'x');

        self::assertSame([false, false, false], [$inspection->accepted(), $inspection->flagged(), $inspection->infected()]);
        self::assertSame([], $events->events);
    }

    public function testAPathIsScannedAndClosed(): void
    {
        $path = Temporary::file(Temporary::eicar(), 'cover.png');
        $quarantine = new RecordingQuarantine();
        $before = \count(get_resources('stream'));

        try {
            $inspection = new UploadScanner(new FakeScanner(), quarantine: $quarantine)->inspectPath($path, 'Cover.PNG');
        } finally {
            Temporary::remove(\dirname($path));
        }

        self::assertSame('q1', $inspection->quarantineId);
        self::assertSame('Cover.PNG', $quarantine->kept['q1'][1]);
        self::assertCount($before, get_resources('stream'));
    }

    public function testAPathThatIsNotAFileIsLeftToTheScanner(): void
    {
        $events = new RecordingDispatcher();
        $scanner = new UploadScanner(new FakeScanner(), new ScanPolicy(OnScanError::AcceptAndFlag), events: $events);

        self::assertEquals(new Inspection(ScanResult::error('The file cannot be read.'), Decision::Flag), $scanner->inspectPath('/no/such/file', 'lost.pdf', 'upload'));
        self::assertEquals([new FileFlagged('lost.pdf', 'The file cannot be read.', 'upload')], $events->events);
        self::assertTrue($scanner->inspectPath(sys_get_temp_dir(), 'dir')->flagged());
    }

    public function testAFileFlaggedWithoutADispatcherIsStillFlagged(): void
    {
        self::assertTrue(new UploadScanner(new FakeScanner(error: 'down'), new ScanPolicy(OnScanError::AcceptAndFlag))->inspectContents('x', 'x')->flagged());
    }

    public function testAValueThatIsNotAStreamIsNeverQuarantined(): void
    {
        $quarantine = new RecordingQuarantine();
        $stream = fopen('php://memory', 'r');
        self::assertIsResource($stream);
        fclose($stream);

        $inspection = new UploadScanner(new CondemningScanner(), quarantine: $quarantine)->inspectStream($stream, 'closed');

        self::assertSame([true, null], [$inspection->infected(), $inspection->quarantineId]);
        self::assertSame([], $quarantine->kept);
    }

    public function testTheFileIsClosedWhenAListenerFails(): void
    {
        $scanner = new UploadScanner(new FakeScanner(), events: new ThrowingDispatcher());
        $path = Temporary::file(Temporary::eicar());
        $before = \count(get_resources('stream'));

        foreach ([static fn (): Inspection => $scanner->inspectPath($path, 'x'), static fn (): Inspection => $scanner->inspectContents(Temporary::eicar(), 'x')] as $inspect) {
            try {
                $inspect();
                self::fail('The listener did not fail.');
            } catch (RuntimeException $runtimeException) {
                self::assertSame('A listener failed.', $runtimeException->getMessage());
            }
        }

        Temporary::remove(\dirname($path));
        self::assertCount($before, get_resources('stream'));
    }

    public function testContentsAreScannedFromATemporaryStreamThatIsClosed(): void
    {
        $before = \count(get_resources('stream'));
        new UploadScanner(new FakeScanner())->inspectContents('x', 'x');

        self::assertCount($before, get_resources('stream'));
    }
}
