<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Application;

use Override;
use Phil\BounceParser\BounceParser;
use Phil\EmailAddressBundle\Application\Bounces\BounceIngestor;
use Phil\EmailAddressBundle\Application\Bounces\BounceNotices;
use Phil\EmailAddressBundle\Application\Bounces\BounceRules;
use Phil\EmailAddressBundle\Application\Bounces\MailboxPoller;
use Phil\EmailAddressBundle\Application\Contract\BounceMailbox;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Application\MailboxMessage;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use Phil\EmailAddressBundle\Infrastructure\InMemory\InMemoryBounceHistoryRepository;
use Phil\EmailAddressBundle\Tests\Unit\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(MailboxPoller::class)]
#[CoversClass(MailboxMessage::class)]
final class MailboxPollerTest extends TestCase
{
    public function testEachMessageIsIngestedThenAcknowledged(): void
    {
        $mailbox = $this->mailbox(['a' => 'postfix-hard.eml', 'b' => 'out-of-office.eml', 'c' => 'delayed.eml']);
        $dispatcher = new RecordingDispatcher();

        self::assertSame(['a' => [BounceDecision::Suspend], 'b' => []], $this->poller($mailbox, $dispatcher)->poll(2));
        self::assertSame(['a', 'b'], $mailbox->acknowledged);
        self::assertSame([2], $mailbox->limits);

        $event = $dispatcher->events[0];
        self::assertInstanceOf(BounceDecided::class, $event);
        self::assertSame(NoticeSource::Mailbox, $event->notice->source);
    }

    public function testTheDefaultLimitIsFifty(): void
    {
        $mailbox = $this->mailbox([]);
        $this->poller($mailbox, new RecordingDispatcher())->poll();

        self::assertSame([50], $mailbox->limits);
    }

    public function testAMessageWhoseIngestionThrowsIsNotAcknowledged(): void
    {
        $mailbox = $this->mailbox(['a' => 'postfix-hard.eml']);
        try {
            $this->poller($mailbox, new class implements EventDispatcherInterface {
                #[Override]
                public function dispatch(object $event): object
                {
                    throw new RuntimeException('The listener failed.');
                }
            })->poll();
            self::fail('The failure of the listener reaches the caller.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The listener failed.', $runtimeException->getMessage());
        }

        self::assertSame([], $mailbox->acknowledged);
    }

    /**
     * @param array<string, string> $fixtures
     *
     * @return BounceMailbox&object{acknowledged: list<string>, limits: list<int>}
     */
    private function mailbox(array $fixtures): BounceMailbox
    {
        return new class($fixtures) implements BounceMailbox {
            /** @var list<string> */
            public array $acknowledged = [];

            /** @var list<int> */
            public array $limits = [];

            /**
             * @param array<string, string> $fixtures
             */
            public function __construct(
                private readonly array $fixtures,
            ) {
            }

            #[Override]
            public function fetch(int $limit): iterable
            {
                $this->limits[] = $limit;

                foreach (\array_slice($this->fixtures, 0, $limit, true) as $id => $fixture) {
                    yield new MailboxMessage($id, (string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/'.$fixture));
                }
            }

            #[Override]
            public function acknowledge(MailboxMessage $message): void
            {
                $this->acknowledged[] = $message->id;
            }
        };
    }

    private function poller(BounceMailbox $mailbox, EventDispatcherInterface $dispatcher): MailboxPoller
    {
        $clock = new MockClock('2026-11-20 10:00:00');

        return new MailboxPoller($mailbox, new BounceIngestor(
            new BounceParser(),
            new BounceNotices(new EmailAddresses('key'), clock: $clock),
            new BounceRules(new InMemoryBounceHistoryRepository($clock), $dispatcher, $clock),
        ));
    }
}
