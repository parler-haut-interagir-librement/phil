<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Application;

use Phil\EmailAddress\Problem;
use Phil\NewsletterBundle\Application\Event\AudienceChanged;
use Phil\NewsletterBundle\Application\Exception\NotFound;
use Phil\NewsletterBundle\Application\Exception\SubscriptionRefused;
use Phil\NewsletterBundle\Application\Message\SendConfirmation;
use Phil\NewsletterBundle\Application\SubscribeOutcome;
use Phil\NewsletterBundle\Application\Subscriptions;
use Phil\NewsletterBundle\Domain\Enum\AudienceEventType;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Enum\UnsubscribeReason;
use Phil\NewsletterBundle\Domain\Model\Delivery;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use Phil\NewsletterBundle\Tests\Unit\Fixture\Newsletter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subscriptions::class)]
#[CoversClass(SubscribeOutcome::class)]
#[CoversClass(SubscriptionRefused::class)]
#[CoversClass(NotFound::class)]
#[CoversClass(AudienceChanged::class)]
final class SubscriptionsTest extends TestCase
{
    private Newsletter $newsletter;

    protected function setUp(): void
    {
        $this->newsletter = new Newsletter();
    }

    public function testADoubleOptInWaitsForTheConfirmation(): void
    {
        $list = $this->newsletter->list();

        self::assertSame(SubscribeOutcome::ConfirmationSent, $this->newsletter->subscriptions->subscribe($list, ' Ada@Example.COM ', 'user-1'));

        $subscriber = $this->subscriber('ada@example.com');
        self::assertSame('Ada@example.com', $subscriber->email);
        self::assertSame('user-1', $subscriber->externalId);
        self::assertSame(SubscriberStatus::Pending, $subscriber->status);
        self::assertSame(MembershipStatus::Pending, $this->newsletter->subscribers->membership($subscriber->id, $list->id)?->status);
        $confirmation = $this->newsletter->bus->messages(SendConfirmation::class);
        self::assertEquals([new SendConfirmation($subscriber->id, $list->id)], $confirmation);
        self::assertSame([AudienceEventType::Subscribed], $this->types());

        self::assertTrue($this->newsletter->subscriptions->confirm($subscriber->id, $list->id));
        self::assertFalse($this->newsletter->subscriptions->confirm($subscriber->id, $list->id));
        self::assertFalse($this->newsletter->subscriptions->confirm($subscriber->id, 'other'));
        $subscriber = $this->subscriber('ada@example.com');
        self::assertSame(SubscriberStatus::Active, $subscriber->status);
        self::assertSame(MembershipStatus::Active, $this->newsletter->subscribers->membership($subscriber->id, $list->id)?->status);
        self::assertSame([AudienceEventType::Subscribed, AudienceEventType::Confirmed], $this->types());
        $event = $this->newsletter->events->events[1];
        self::assertInstanceOf(AudienceChanged::class, $event);
        self::assertSame([$subscriber->id, 'default', $list->id, 'user-1'], [$event->subscriberId, $event->site, $event->listId, $event->externalId]);
        self::assertSame(32, \strlen($event->id()));

        self::assertSame(SubscribeOutcome::AlreadySubscribed, $this->newsletter->subscriptions->subscribe($list, 'ada@example.com', 'user-2'));
        self::assertSame('user-1', $this->subscriber('ada@example.com')->externalId);
    }

    public function testASingleOptInSubscribesAtOnce(): void
    {
        $list = $this->newsletter->list('news', false);

        self::assertSame(SubscribeOutcome::Subscribed, $this->newsletter->subscriptions->subscribe($list, 'ada@example.com'));
        self::assertSame(SubscriberStatus::Active, $this->subscriber('ada@example.com')->status);
        self::assertSame([], $this->newsletter->bus->messages(SendConfirmation::class));
        self::assertSame([AudienceEventType::Subscribed, AudienceEventType::Confirmed], $this->types());

        $other = $this->newsletter->list('events', true);
        self::assertSame(SubscribeOutcome::Subscribed, $this->newsletter->subscriptions->subscribe($other, 'ada@example.com', 'u'));
        self::assertSame('u', $this->subscriber('ada@example.com')->externalId);
        self::assertSame([AudienceEventType::Subscribed, AudienceEventType::Confirmed, AudienceEventType::Confirmed], $this->types());
    }

    public function testASubscriberWhoLeftSubscribesAgainWithAConfirmation(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->newsletter->subscriptions->subscribe($list, 'ada@example.com');
        $id = $this->subscriber('ada@example.com')->id;
        $this->newsletter->subscriptions->leave($id);

        $double = $this->newsletter->list('weekly');
        self::assertSame(SubscribeOutcome::ConfirmationSent, $this->newsletter->subscriptions->subscribe($double, 'ada@example.com'));
        self::assertSame(SubscriberStatus::Pending, $this->subscriber('ada@example.com')->status);
        self::assertSame(SubscribeOutcome::Subscribed, $this->newsletter->subscriptions->subscribe($list, 'ada@example.com'));
        self::assertSame(SubscriberStatus::Active, $this->subscriber('ada@example.com')->status);
    }

    public function testAPausedSubscriberStaysPausedWhenJoiningAList(): void
    {
        $list = $this->newsletter->list('news', false);
        $this->newsletter->subscriptions->subscribe($list, 'ada@example.com');
        $id = $this->subscriber('ada@example.com')->id;
        $this->newsletter->subscriptions->pause($id, $this->newsletter->clock->now()->modify('+1 week'));

        self::assertSame(SubscribeOutcome::Subscribed, $this->newsletter->subscriptions->subscribe($this->newsletter->list('weekly'), 'ada@example.com'));
        self::assertSame(SubscriberStatus::Paused, $this->subscriber('ada@example.com')->status);
    }

    public function testASuppressedAddressIsSentNothing(): void
    {
        $list = $this->newsletter->list();
        $this->newsletter->suppressions->suppress(Site::default(), 'bounced@example.com', 'bounced');
        self::assertSame(SubscribeOutcome::Suppressed, $this->newsletter->subscriptions->subscribe($list, 'Bounced@example.com'));

        $subscriber = new Subscriber('s', Site::default(), 'complained@example.com', null, SubscriberStatus::Complained, $this->newsletter->clock->now());
        $this->newsletter->subscribers->save($subscriber);
        self::assertSame(SubscribeOutcome::Suppressed, $this->newsletter->subscriptions->subscribe($list, 'complained@example.com'));
        self::assertSame([], $this->newsletter->bus->envelopes);
        self::assertSame([], $this->newsletter->events->events);
        self::assertFalse($this->newsletter->subscriptions->confirm('s', $list->id));
    }

    public function testARefusedAddressIsNotKept(): void
    {
        try {
            $this->newsletter->subscriptions->subscribe($this->newsletter->list(), 'ada@example.notatld');
            self::fail('Expected a refusal.');
        } catch (SubscriptionRefused $subscriptionRefused) {
            self::assertSame([Problem::UnknownTopLevelDomain], $subscriptionRefused->problems);
        }

        try {
            $this->newsletter->subscriptions->subscribe($this->newsletter->list(), 'not an address');
            self::fail('Expected a refusal.');
        } catch (SubscriptionRefused $subscriptionRefused) {
            self::assertSame([Problem::Syntax], $subscriptionRefused->problems);
            self::assertSame('The address is refused: syntax.', $subscriptionRefused->getMessage());
        }

        self::assertNull($this->newsletter->subscribers->byEmail(Site::default(), 'not an address'));
    }

    public function testLeavingOneListThenTheLast(): void
    {
        $news = $this->newsletter->list('news', false);
        $events = $this->newsletter->list('events', false);
        $this->newsletter->subscriptions->subscribe($news, 'ada@example.com');
        $this->newsletter->subscriptions->subscribe($events, 'ada@example.com');

        $id = $this->subscriber('ada@example.com')->id;

        self::assertTrue($this->newsletter->subscriptions->leave($id, $news->id, UnsubscribeReason::TooFrequent));
        self::assertSame(SubscriberStatus::Active, $this->subscriber('ada@example.com')->status);
        self::assertFalse($this->newsletter->subscriptions->leave($id, $news->id));
        self::assertTrue($this->newsletter->subscriptions->leave($id, $events->id));
        self::assertSame(SubscriberStatus::Unsubscribed, $this->subscriber('ada@example.com')->status);
        self::assertFalse($this->newsletter->subscriptions->leave($id));
        self::assertSame(['too_frequent' => 1], $this->newsletter->engagement->unsubscribeReasons(Site::default(), $this->newsletter->clock->now()));
        self::assertSame(AudienceEventType::Unsubscribed, end($this->newsletter->events->events) instanceof AudienceChanged ? end($this->newsletter->events->events)->type : null);
    }

    public function testLeavingEverything(): void
    {
        $news = $this->newsletter->list('news', false);
        $this->newsletter->list('events', false);
        $this->newsletter->subscriptions->subscribe($news, 'ada@example.com');

        $id = $this->subscriber('ada@example.com')->id;

        self::assertTrue($this->newsletter->subscriptions->leave($id, null, UnsubscribeReason::Other));
        self::assertSame(SubscriberStatus::Unsubscribed, $this->subscriber('ada@example.com')->status);
        self::assertSame(MembershipStatus::Unsubscribed, $this->newsletter->subscribers->membership($id, $news->id)?->status);
    }

    public function testJoiningAnotherList(): void
    {
        $news = $this->newsletter->list('news', false);
        $events = $this->newsletter->list('events');
        $elsewhere = $this->newsletter->list('news', false, 'shop');
        $this->newsletter->subscriptions->subscribe($news, 'ada@example.com');
        $id = $this->subscriber('ada@example.com')->id;

        self::assertTrue($this->newsletter->subscriptions->join($id, $events));
        self::assertFalse($this->newsletter->subscriptions->join($id, $events));
        self::assertFalse($this->newsletter->subscriptions->join($id, $elsewhere));
        self::assertSame(MembershipStatus::Active, $this->newsletter->subscribers->membership($id, $events->id)?->status);

        $this->newsletter->subscriptions->leave($id);
        self::assertFalse($this->newsletter->subscriptions->join($id, $news));
    }

    public function testPausingAndResuming(): void
    {
        $news = $this->newsletter->list('news', false);
        $this->newsletter->subscriptions->subscribe($news, 'ada@example.com');
        $id = $this->subscriber('ada@example.com')->id;
        $this->newsletter->events->events = [];

        $this->newsletter->subscriptions->pause($id, $this->newsletter->clock->now()->modify('+4 weeks'));
        self::assertSame(SubscriberStatus::Paused, $this->subscriber('ada@example.com')->status);
        $this->newsletter->subscriptions->resume($id);
        self::assertSame(SubscriberStatus::Active, $this->subscriber('ada@example.com')->status);
        $this->newsletter->subscriptions->resume($id);
        self::assertSame([AudienceEventType::Paused, AudienceEventType::Resumed], $this->types());
    }

    public function testForgettingASubscriber(): void
    {
        $news = $this->newsletter->list('news', false);
        $this->newsletter->subscriptions->subscribe($news, 'ada@example.com');
        $subscriber = $this->subscriber('ada@example.com');
        $this->newsletter->deliveries->create(new Delivery('d1', $subscriber->id, null, 't', $this->newsletter->clock->now()));
        $this->newsletter->engagement->recordRecipientClick('c', 0, $subscriber->id, $this->newsletter->clock->now());

        $this->newsletter->subscriptions->forget($subscriber);

        self::assertNull($this->newsletter->subscribers->get($subscriber->id));
        self::assertSame([], $this->newsletter->subscribers->memberships($subscriber->id));
        self::assertNull($this->newsletter->deliveries->get('d1'));
        self::assertSame([], $this->newsletter->engagement->recipientClicks($subscriber->id));
        self::assertFalse($this->newsletter->suppressions->isSuppressed(Site::default(), 'ada@example.com'));
        self::assertSame(AudienceEventType::Erased, end($this->newsletter->events->events) instanceof AudienceChanged ? end($this->newsletter->events->events)->type : null);

        $bounced = new Subscriber('b', Site::default(), 'gone@example.com', null, SubscriberStatus::Bounced, $this->newsletter->clock->now());
        $this->newsletter->subscriptions->forget($bounced);
        self::assertTrue($this->newsletter->suppressions->isSuppressed(Site::default(), 'gone@example.com'));
    }

    public function testWhatIsNotThere(): void
    {
        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('No subscriber "nobody".');

        $this->newsletter->subscriptions->leave('nobody');
    }

    public function testFindingAList(): void
    {
        $list = $this->newsletter->list();
        self::assertEquals($list, $this->newsletter->subscriptions->list($list->id));

        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('No list "none".');
        $this->newsletter->subscriptions->list('none');
    }

    private function subscriber(string $email): Subscriber
    {
        $subscriber = $this->newsletter->subscribers->byEmail(Site::default(), $email);
        self::assertInstanceOf(Subscriber::class, $subscriber);

        return $subscriber;
    }

    /**
     * @return list<AudienceEventType>
     */
    private function types(): array
    {
        return array_map(static fn (object $event): AudienceEventType => $event instanceof AudienceChanged ? $event->type : AudienceEventType::Erased, $this->newsletter->events->events);
    }
}
