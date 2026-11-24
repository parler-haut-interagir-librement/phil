<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Phil\EmailAddressBundle\Application\Bounces\BounceNotices;
use Phil\EmailAddressBundle\Application\Bounces\BounceRules;
use Phil\EmailAddressBundle\Application\EmailAddresses;
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Phil\EmailAddressBundle\Domain\NoticeKind;
use Phil\EmailAddressBundle\Domain\NoticeSource;
use Phil\EmailAddressBundle\Infrastructure\InMemory\InMemoryBounceHistoryRepository;
use Phil\EmailAddressBundle\Infrastructure\RemoteEvent\MailerEventConsumer;
use Phil\EmailAddressBundle\Tests\Unit\RecordingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerEngagementEvent;
use Symfony\Component\RemoteEvent\RemoteEvent;

#[CoversClass(MailerEventConsumer::class)]
final class MailerEventConsumerTest extends TestCase
{
    private RecordingDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new RecordingDispatcher();
    }

    #[TestWith([MailerDeliveryEvent::BOUNCE, NoticeKind::Hard, BounceDecision::Suspend])]
    #[TestWith([MailerDeliveryEvent::DROPPED, NoticeKind::Hard, BounceDecision::Suspend])]
    #[TestWith([MailerDeliveryEvent::DEFERRED, NoticeKind::Soft, BounceDecision::Count])]
    public function testADeliveryEventIsABounce(string $name, NoticeKind $kind, BounceDecision $decision): void
    {
        $this->consumer()->consume($this->delivery($name));

        $event = $this->decided();
        self::assertSame([$kind, $decision, NoticeSource::Webhook, 'webhook:evt-1', 'ada@example.org', true], [$event->notice->kind, $event->decision, $event->notice->source, $event->notice->reportKey, $event->notice->address, $event->notice->authenticated]);
    }

    #[TestWith([MailerEngagementEvent::SPAM, NoticeKind::Complaint])]
    #[TestWith([MailerEngagementEvent::UNSUBSCRIBE, NoticeKind::Unsubscribe])]
    public function testAComplaintOrARequestToLeaveOfASignedWebhookUnsubscribes(string $name, NoticeKind $kind): void
    {
        $engagement = new MailerEngagementEvent($name, 'evt-2', []);
        $engagement->setRecipientEmail('ada@example.org');
        $this->consumer()->consume($engagement);

        $event = $this->decided();
        self::assertSame([$kind, BounceDecision::Unsubscribe], [$event->notice->kind, $event->decision]);
    }

    #[TestWith([MailerDeliveryEvent::DELIVERED])]
    #[TestWith([MailerDeliveryEvent::RECEIVED])]
    public function testADeliveryIsNoNotice(string $name): void
    {
        $this->consumer()->consume($this->delivery($name));

        self::assertSame([], $this->dispatcher->events);
    }

    #[TestWith([MailerEngagementEvent::OPEN])]
    #[TestWith([MailerEngagementEvent::CLICK])]
    public function testAnEngagementIsNoNotice(string $name): void
    {
        $this->consumer()->consume(new MailerEngagementEvent($name, 'evt-3', []));

        self::assertSame([], $this->dispatcher->events);
    }

    public function testAnotherRemoteEventIsIgnored(): void
    {
        $this->consumer()->consume(new RemoteEvent('bounce', 'evt-4', []));

        self::assertSame([], $this->dispatcher->events);
    }

    public function testTheSameEventSentAgainIsDecidedOnce(): void
    {
        $consumer = $this->consumer();
        $consumer->consume($this->delivery(MailerDeliveryEvent::DEFERRED));
        $consumer->consume($this->delivery(MailerDeliveryEvent::DEFERRED));

        self::assertCount(1, $this->dispatcher->events);
    }

    public function testTheRecipientIsReadFromTheMetadataTheConfigurationNames(): void
    {
        $this->consumer('recipient')->consume($this->delivery(MailerDeliveryEvent::BOUNCE, ['recipient' => 'm-42']));
        $this->consumer('recipient')->consume($this->delivery(MailerDeliveryEvent::BOUNCE, ['recipient' => ''], 'evt-5'));
        $this->consumer('recipient')->consume($this->delivery(MailerDeliveryEvent::BOUNCE, ['recipient' => 42], 'evt-6'));
        $this->consumer('recipient')->consume($this->delivery(MailerDeliveryEvent::BOUNCE, [], 'evt-7'));
        $this->consumer()->consume($this->delivery(MailerDeliveryEvent::BOUNCE, ['recipient' => 'm-42'], 'evt-8'));

        self::assertSame(['m-42', null, null, null, null], array_map(static fn (object $event): ?string => $event instanceof BounceDecided ? $event->notice->recipientId : 'none', $this->dispatcher->events));
    }

    private function decided(): BounceDecided
    {
        self::assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];
        self::assertInstanceOf(BounceDecided::class, $event);

        return $event;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function delivery(string $name, array $metadata = [], string $id = 'evt-1'): MailerDeliveryEvent
    {
        $event = new MailerDeliveryEvent($name, $id, []);
        $event->setRecipientEmail('ada@example.org');
        $event->setMetadata($metadata);

        return $event;
    }

    private function consumer(?string $metadata = null): MailerEventConsumer
    {
        $clock = new MockClock('2026-11-20 10:00:00');

        return new MailerEventConsumer(
            new BounceNotices(new EmailAddresses('key'), clock: $clock),
            new BounceRules(new InMemoryBounceHistoryRepository($clock), $this->dispatcher, $clock),
            $metadata,
        );
    }
}
