<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Application;

use InvalidArgumentException;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\OnScanError;
use Phil\UploadScan\ScanPolicy;
use Phil\UploadScanBundle\Application\Event\FileFlagged;
use Phil\UploadScanBundle\Application\Event\FileInfected;
use Phil\UploadScanBundle\Application\Event\FileRescanned;
use Phil\UploadScanBundle\Application\Exception\UnknownRescanTarget;
use Phil\UploadScanBundle\Application\Message\RescanFile;
use Phil\UploadScanBundle\Application\RescanFileHandler;
use Phil\UploadScanBundle\Application\UploadScanner;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Manuscripts;
use Phil\UploadScanBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\UploadScanBundle\Tests\Unit\Fixture\Temporary;
use Phil\UploadScanBundle\Tests\Unit\Fixture\ThrowingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(RescanFileHandler::class)]
#[CoversClass(RescanFile::class)]
#[CoversClass(UnknownRescanTarget::class)]
#[CoversClass(FileRescanned::class)]
final class RescanFileHandlerTest extends TestCase
{
    public function testTheFileIsInspectedAgainAndTheInspectionHandedToItsTarget(): void
    {
        $events = new RecordingDispatcher();
        $manuscripts = new Manuscripts(['m42' => Temporary::eicar(), 'm43' => 'a chapter']);
        $handler = new RescanFileHandler(new UploadScanner(new FakeScanner(), events: $events), [new Manuscripts([], 'covers'), $manuscripts]);
        $before = \count(get_resources('stream'));

        $handler(new RescanFile('manuscripts', 'm42'));
        $handler(new RescanFile('manuscripts', 'm43'));

        self::assertSame(['m42', 'm43'], array_keys($manuscripts->resolved));
        self::assertTrue($manuscripts->resolved['m42']->infected());
        self::assertTrue($manuscripts->resolved['m43']->accepted());
        self::assertEquals([new FileInfected('m42', 'Eicar-Test-Signature', 'rescan.manuscripts')], $events->events);
        self::assertCount($before, get_resources('stream'));
    }

    public function testFileRescannedFollowsTheResolutionOfTheTarget(): void
    {
        $events = new RecordingDispatcher();
        $manuscripts = new Manuscripts(['m42' => Temporary::eicar(), 'm43' => 'a chapter']);
        $handler = new RescanFileHandler(new UploadScanner(new FakeScanner(), events: $events), [$manuscripts], $events);

        $handler(new RescanFile('manuscripts', 'm42'));
        $handler(new RescanFile('manuscripts', 'm43'));

        self::assertCount(3, $events->events);
        self::assertInstanceOf(FileInfected::class, $events->events[0]);
        [, $infected, $clean] = $events->events;
        self::assertInstanceOf(FileRescanned::class, $infected);
        self::assertInstanceOf(FileRescanned::class, $clean);
        self::assertEquals(new FileRescanned('manuscripts', 'm42', $manuscripts->resolved['m42']), $infected);
        self::assertEquals(new FileRescanned('manuscripts', 'm43', $manuscripts->resolved['m43']), $clean);
        self::assertSame([false, true], [$infected->cleared(), $clean->cleared()]);
    }

    public function testAFileStillUnscannedIsNotCleared(): void
    {
        $events = new RecordingDispatcher();
        $manuscripts = new Manuscripts(['m1' => 'a chapter']);
        $scanner = new UploadScanner(new FakeScanner(error: 'clamd is down'), new ScanPolicy(OnScanError::AcceptAndFlag), events: $events);

        new RescanFileHandler($scanner, [$manuscripts], $events)(new RescanFile('manuscripts', 'm1'));

        self::assertEquals(new FileFlagged('m1', 'clamd is down', 'rescan.manuscripts'), $events->events[0]);
        $rescanned = $events->events[1] ?? null;
        self::assertInstanceOf(FileRescanned::class, $rescanned);
        self::assertSame([true, false], [$rescanned->inspection->flagged(), $rescanned->cleared()]);
    }

    public function testNothingIsAnnouncedWhenTheTargetFails(): void
    {
        $events = new RecordingDispatcher();
        $manuscripts = new Manuscripts(['m1' => 'a chapter'], failsToResolve: true);

        try {
            new RescanFileHandler(new UploadScanner(new FakeScanner()), [$manuscripts], $events)(new RescanFile('manuscripts', 'm1'));
            self::fail('The target did not fail.');
        } catch (RuntimeException) {
            self::assertSame([], $events->events);
        }
    }

    public function testTheFileIsClosedWhenAListenerFails(): void
    {
        $manuscripts = new Manuscripts(['m1' => Temporary::eicar()]);
        $before = \count(get_resources('stream'));

        try {
            new RescanFileHandler(new UploadScanner(new FakeScanner(), events: new ThrowingDispatcher()), [$manuscripts])(new RescanFile('manuscripts', 'm1'));
            self::fail('The listener did not fail.');
        } catch (RuntimeException) {
            self::assertCount($before, get_resources('stream'));
            self::assertSame([], $manuscripts->resolved);
        }
    }

    public function testAFileThatNoLongerExistsIsLeftAlone(): void
    {
        $manuscripts = new Manuscripts();

        new RescanFileHandler(new UploadScanner(new FakeScanner()), [$manuscripts])(new RescanFile('manuscripts', 'gone'));

        self::assertSame([], $manuscripts->resolved);
    }

    public function testAnUnknownTargetIsRefused(): void
    {
        $this->expectException(UnknownRescanTarget::class);
        $this->expectExceptionMessageIsOrContains('No rescan target is named "covers".');

        new RescanFileHandler(new UploadScanner(new FakeScanner()), [new Manuscripts()])(new RescanFile('covers', '1'));
    }

    public function testAMessageCarriesTwoShortIdentifiers(): void
    {
        $message = new RescanFile(str_repeat('t', 255), 'f');
        self::assertSame([str_repeat('t', 255), 'f'], [$message->target, $message->fileId]);

        foreach ([['', 'f', 'target'], [str_repeat('t', 256), 'f', 'target'], ['t', '', 'file identifier'], ['t', str_repeat('f', 256), 'file identifier']] as [$target, $fileId, $what]) {
            try {
                new RescanFile($target, $fileId);
                self::fail('A rescan was accepted.');
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(\sprintf('The %s of a rescan is 1 to 255 bytes.', $what), $invalidArgumentException->getMessage());
            }
        }
    }
}
