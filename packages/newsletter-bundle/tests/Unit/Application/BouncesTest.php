<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use Phil\BounceParser\BounceKind;
use Phil\BounceParser\BounceParser;
use Phil\BounceParser\Verp;
use Phil\NewsletterBundle\Application\Bounces\BounceHandler;
use Phil\NewsletterBundle\Application\Bounces\BounceOutcome;
use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Application\Handler\FetchBouncesHandler;
use Phil\NewsletterBundle\Application\Message\FetchBounces;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;
use Phil\NewsletterBundle\Domain\Enum\DeliveryStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Infrastructure\Site\ConfiguredSite;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use Phil\NewsletterBundle\Tests\Unit\Fixture\ScriptedMailbox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BounceHandler::class)]
#[CoversClass(BounceOutcome::class)]
#[CoversClass(FetchBouncesHandler::class)]
final class BouncesTest extends TestCase
{
    private const string KEY = 'a-verp-key-of-sixteen-bytes';

    private Newsletter $newsletter;

    private Verp $verp;

    protected function setUp(): void
    {
        $this->newsletter = new Newsletter();
        $this->verp = new Verp('bounces', 'example.com');
    }

    public function testAHardBounceNamedByItsReturnPathSuppressesTheAddress(): void
    {
        $ada = $this->subscriber('ada@example.org');
        $this->newsletter->deliveries->create(new Delivery('d1', $ada->id, 'c1', null, $this->newsletter->clock->now()));

        self::assertSame([BounceOutcome::Bounced], $this->handler()->handleMessage($this->dsn('someone-else@example.org', '5.1.1', $this->verp->encodeSigned('d1', self::KEY))));

        self::assertSame(SubscriberStatus::Bounced, $this->newsletter->subscribers->get($ada->id)?->status);
        self::assertSame(DeliveryStatus::Bounced, $this->newsletter->deliveries->get('d1')?->status);
        self::assertTrue($this->newsletter->suppressions->isSuppressed(Site::default(), 'ada@example.org'));
        $event = end($this->newsletter->events->events);
        self::assertInstanceOf(AudienceChanged::class, $event);
        self::assertSame(AudienceEventType::Bounced, $event->type);
    }

    public function testABounceWithoutReturnPathIsFoundByItsAddress(): void
    {
        $ada = $this->subscriber('ada@example.org');

        self::assertSame([BounceOutcome::Bounced], $this->handler()->handleMessage($this->dsn('ada@example.org', '5.1.1', 'bounces@example.com')));
        self::assertSame(SubscriberStatus::Bounced, $this->newsletter->subscribers->get($ada->id)?->status);
        self::assertSame([BounceOutcome::Ignored], $this->handler()->handleMessage($this->dsn('nobody@example.org', '5.1.1', 'bounces@example.com')));
    }

    public function testSoftBouncesAreCountedToTheThreshold(): void
    {
        $ada = $this->subscriber('ada@example.org');
        $soft = $this->dsn('ada@example.org', '4.2.2', 'bounces@example.com');
        $this->newsletter->events->events = [];

        self::assertSame([BounceOutcome::Counted], $this->handler()->handleMessage($soft));
        self::assertSame([BounceOutcome::Counted], $this->handler()->handleMessage($soft));
        self::assertFalse($this->newsletter->suppressions->isSuppressed(Site::default(), 'ada@example.org'));
        self::assertSame([], $this->newsletter->events->events);
        self::assertSame([BounceOutcome::Bounced], $this->handler()->handleMessage($soft));
        self::assertSame(SubscriberStatus::Bounced, $this->newsletter->subscribers->get($ada->id)?->status);
    }

    public function testAnAutomaticReplyChangesNothing(): void
    {
        $this->subscriber('ada@example.org');

        self::assertSame([BounceOutcome::Ignored], $this->handler()->handleMessage("From: ada@example.org\r\nTo: bounces@example.com\r\nSubject: Out of office\r\nAuto-Submitted: auto-replied\r\n\r\nI am away."));
        self::assertSame([BounceOutcome::Ignored], $this->handler()->handleMessage('not a message'));
    }

    public function testProviderEvents(): void
    {
        $ada = $this->subscriber('ada@example.org');
        $bob = $this->subscriber('bob@example.org');
        $carl = $this->subscriber('carl@example.org');
        $this->newsletter->deliveries->create(new Delivery('d1', $bob->id, null, 't', $this->newsletter->clock->now()));

        self::assertSame(BounceOutcome::Complained, $this->handler()->handleEvent(BounceKind::Complaint, 'ada@example.org', null));
        self::assertSame(SubscriberStatus::Complained, $this->newsletter->subscribers->get($ada->id)?->status);
        self::assertSame(BounceOutcome::Bounced, $this->handler()->handleEvent(BounceKind::Hard, 'whatever@example.org', 'd1'));
        self::assertSame(SubscriberStatus::Bounced, $this->newsletter->subscribers->get($bob->id)?->status);
        self::assertSame(BounceOutcome::Unsubscribed, $this->handler()->handleEvent(BounceKind::Feedback, 'carl@example.org', null));
        self::assertSame(SubscriberStatus::Unsubscribed, $this->newsletter->subscribers->get($carl->id)?->status);
        self::assertSame(BounceOutcome::Ignored, $this->handler()->handleEvent(BounceKind::Feedback, 'carl@example.org', null));
        self::assertSame(BounceOutcome::Ignored, $this->handler()->handleEvent(BounceKind::Delivered, 'carl@example.org', null));
        self::assertSame(BounceOutcome::Ignored, $this->handler()->handleEvent(BounceKind::Hard, 'nobody@example.org', null));
        self::assertSame(BounceOutcome::Counted, $this->handler()->handleEvent(BounceKind::Soft, 'carl@example.org', null));
        $events = array_map(static fn (object $event): string => $event instanceof AudienceChanged ? $event->type->value : '', $this->newsletter->events->events);
        self::assertSame(['subscribed', 'confirmed', 'subscribed', 'confirmed', 'subscribed', 'confirmed', 'complained', 'bounced', 'unsubscribed'], $events);
    }

    public function testTheMailboxIsReadAndEachMessageAcknowledged(): void
    {
        $this->subscriber('ada@example.org');
        $mailbox = new ScriptedMailbox(['7' => $this->dsn('ada@example.org', '5.1.1', 'bounces@example.com'), '9' => 'nothing']);

        self::assertSame(2, new FetchBouncesHandler($mailbox, $this->handler())(new FetchBounces(5)));
        self::assertSame(['7', '9'], $mailbox->acknowledged);
        self::assertSame(5, $mailbox->limit);
        self::assertSame(0, new FetchBouncesHandler(new ScriptedMailbox([]), $this->handler())(new FetchBounces(0)));
    }

    private function handler(): BounceHandler
    {
        return new BounceHandler(new BounceParser(verp: $this->verp), $this->newsletter->subscribers, $this->newsletter->deliveries, $this->newsletter->suppressions, $this->newsletter->subscriptions, new ConfiguredSite(), $this->newsletter->clock, $this->verp, self::KEY);
    }

    private function subscriber(string $email): Subscriber
    {
        $this->newsletter->subscriptions->subscribe($this->newsletter->list('news', false), $email);
        $subscriber = $this->newsletter->subscribers->byEmail(Site::default(), $email);
        self::assertInstanceOf(Subscriber::class, $subscriber);

        return $subscriber;
    }

    private function dsn(string $recipient, string $status, string $deliveredTo): string
    {
        return implode("\r\n", [
            'Delivered-To: '.$deliveredTo,
            'From: MAILER-DAEMON@mx.example.org',
            'To: '.$deliveredTo,
            'Subject: Undelivered Mail Returned to Sender',
            'MIME-Version: 1.0',
            'Content-Type: multipart/report; report-type=delivery-status; boundary="b"',
            '',
            '--b',
            'Content-Type: text/plain',
            '',
            'The message could not be delivered.',
            '--b',
            'Content-Type: message/delivery-status',
            '',
            'Reporting-MTA: dns; mx.example.org',
            '',
            'Final-Recipient: rfc822; '.$recipient,
            'Action: '.(str_starts_with($status, '5') ? 'failed' : 'delayed'),
            'Status: '.$status,
            'Diagnostic-Code: smtp; 550 '.$status.' No such user',
            '',
            '--b--',
            '',
        ]);
    }
}
