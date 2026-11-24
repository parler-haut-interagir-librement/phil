<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Application;

use Phil\BounceParser\BounceParser;
use Phil\EmailAddressBundle\Application\Bounces\BounceIngestor;
use Phil\EmailAddressBundle\Application\Bounces\BounceNotices;
use Phil\EmailAddressBundle\Application\Bounces\BounceRules;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use Phil\EmailAddressBundle\Infrastructure\InMemory\InMemoryBounceHistoryRepository;
use Phil\EmailAddressBundle\Tests\Unit\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(BounceIngestor::class)]
final class BounceIngestorTest extends TestCase
{
    public function testARawMessageIsParsedAndEachNoticeDecidedOnce(): void
    {
        $dispatcher = new RecordingDispatcher();
        $ingestor = $this->ingestor($dispatcher);
        $raw = (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/postfix-hard.eml');

        self::assertSame([BounceDecision::Suspend], $ingestor->ingest($raw));
        self::assertSame([BounceDecision::Duplicate], $ingestor->ingest($raw));
        self::assertSame([BounceDecision::Suspend], $ingestor->ingest($raw."\n"));

        $event = $dispatcher->events[0];
        self::assertInstanceOf(BounceDecided::class, $event);
        self::assertSame('raw:'.hash('sha256', $raw), $event->notice->reportKey);
        self::assertSame(NoticeSource::Message, $event->notice->source);
    }

    public function testTheSourceIsGiven(): void
    {
        $dispatcher = new RecordingDispatcher();
        $this->ingestor($dispatcher)->ingest((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/delayed.eml'), NoticeSource::Mailbox);

        $event = $dispatcher->events[0];
        self::assertInstanceOf(BounceDecided::class, $event);
        self::assertSame(NoticeSource::Mailbox, $event->notice->source);
    }

    public function testAnIgnoredMessageDecidesNothing(): void
    {
        $dispatcher = new RecordingDispatcher();

        self::assertSame([], $this->ingestor($dispatcher)->ingest((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/out-of-office.eml')));
        self::assertSame([], $dispatcher->events);
    }

    private function ingestor(RecordingDispatcher $dispatcher): BounceIngestor
    {
        $clock = new MockClock('2026-11-20 10:00:00');

        return new BounceIngestor(
            new BounceParser(),
            new BounceNotices(new EmailAddresses('key'), clock: $clock),
            new BounceRules(new InMemoryBounceHistoryRepository($clock), $dispatcher, $clock),
        );
    }
}
