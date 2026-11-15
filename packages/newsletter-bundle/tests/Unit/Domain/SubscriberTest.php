<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\MembershipStatus;
use Phil\NewsletterBundle\Domain\Enum\SubscriberStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\Model\Membership;
use Phil\NewsletterBundle\Domain\Model\Subscriber;
use Phil\NewsletterBundle\Domain\ValueObject\Site;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subscriber::class)]
#[CoversClass(Membership::class)]
#[CoversClass(Site::class)]
#[CoversClass(InvalidNewsletterValue::class)]
final class SubscriberTest extends TestCase
{
    public function testASubscriberGoesThroughItsStates(): void
    {
        $now = new DateTimeImmutable('@1800000000');
        $subscriber = $this->subscriber();

        self::assertSame('ada@example.com', $subscriber->emailKey());
        self::assertSame('ada@example.com', Subscriber::keyOf(' Ada@Example.COM '));
        self::assertFalse($subscriber->canReceive($now));

        $subscriber->activate($now);
        self::assertSame(SubscriberStatus::Active, $subscriber->status);
        self::assertSame($now, $subscriber->confirmedAt);
        self::assertTrue($subscriber->canReceive($now));

        $subscriber->activate($now->modify('+1 day'));
        self::assertSame($now, $subscriber->confirmedAt);

        $subscriber->pause($now->modify('+1 week'), $now);
        self::assertSame(SubscriberStatus::Paused, $subscriber->status);
        self::assertFalse($subscriber->canReceive($now->modify('+6 days')));
        self::assertTrue($subscriber->canReceive($now->modify('+1 week')));
        $subscriber->pause($now->modify('+2 weeks'), $now);
        self::assertSame('2027-01-29', $subscriber->pausedUntil?->format('Y-m-d'));

        $subscriber->resume();
        self::assertSame(SubscriberStatus::Active, $subscriber->status);
        self::assertNull($subscriber->pausedUntil);
        $subscriber->resume();
        self::assertSame(SubscriberStatus::Active, $subscriber->status);

        $subscriber->awaitConfirmation();
        self::assertSame(SubscriberStatus::Active, $subscriber->status);

        $subscriber->pause($now->modify('+1 day'), $now);
        $subscriber->unsubscribe();
        self::assertSame(SubscriberStatus::Unsubscribed, $subscriber->status);
        self::assertNull($subscriber->pausedUntil);
        $subscriber->awaitConfirmation();
        self::assertSame(SubscriberStatus::Pending, $subscriber->status);
    }

    public function testBouncesAndComplaintsSuppress(): void
    {
        $subscriber = $this->subscriber(SubscriberStatus::Active);

        self::assertFalse($subscriber->softBounce(3));
        self::assertFalse($subscriber->softBounce(3));
        self::assertSame(2, $subscriber->softBounces);
        $subscriber->delivered();
        self::assertSame(0, $subscriber->softBounces);
        self::assertFalse($subscriber->softBounce(2));
        self::assertTrue($subscriber->softBounce(2));
        self::assertSame(SubscriberStatus::Bounced, $subscriber->status);
        self::assertTrue($subscriber->isSuppressed());
        self::assertFalse($subscriber->canReceive(new DateTimeImmutable()));

        $subscriber->unsubscribe();
        self::assertSame(SubscriberStatus::Bounced, $subscriber->status);
        $subscriber->complained();
        self::assertSame(SubscriberStatus::Complained, $subscriber->status);
        $subscriber->bounced();
        self::assertSame(SubscriberStatus::Complained, $subscriber->status);
        self::assertTrue($this->subscriber(SubscriberStatus::Active)->softBounce(0));

        $this->expectException(InvalidNewsletterValue::class);
        $this->expectExceptionMessageIsOrContains('An address that bounced or complained is never subscribed again.');
        $subscriber->activate(new DateTimeImmutable());
    }

    public function testASuppressedSubscriberDoesNotWaitForAConfirmation(): void
    {
        $subscriber = $this->subscriber(SubscriberStatus::Bounced);

        $this->expectException(InvalidNewsletterValue::class);
        $subscriber->awaitConfirmation();
    }

    public function testOnlyAnActiveSubscriberPausesInTheFuture(): void
    {
        $now = new DateTimeImmutable('@1800000000');

        foreach ([[$this->subscriber(), $now->modify('+1 day'), 'Only an active subscriber pauses.'], [$this->subscriber(SubscriberStatus::Active), $now, 'A pause ends in the future.']] as [$subscriber, $until, $message]) {
            try {
                $subscriber->pause($until, $now);
                self::fail('Expected a refusal.');
            } catch (InvalidNewsletterValue $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }
    }

    public function testTheExternalIdIsAnIdentifier(): void
    {
        $subscriber = $this->subscriber();
        $subscriber->linkExternalId(str_repeat('x', 191));
        self::assertSame(191, \strlen((string) $subscriber->externalId));
        $subscriber->linkExternalId(null);
        self::assertNull($subscriber->externalId);

        foreach (['', str_repeat('x', 192), "a\nb"] as $bad) {
            try {
                $subscriber->linkExternalId($bad);
                self::fail('Expected a refusal.');
            } catch (InvalidNewsletterValue $refused) {
                self::assertSame('An external id is 1 to 191 bytes, without control characters.', $refused->getMessage());
            }
        }
    }

    public function testRestoreKeepsEveryField(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $subscriber = Subscriber::restore('s', Site::of('shop'), 'a@example.com', 'u1', SubscriberStatus::Paused, $at, $at, $at, -3);

        self::assertSame('shop', (string) $subscriber->site);
        self::assertSame('u1', $subscriber->externalId);
        self::assertSame($at, $subscriber->confirmedAt);
        self::assertSame($at, $subscriber->pausedUntil);
        self::assertSame(0, $subscriber->softBounces);
        self::assertSame($at, $subscriber->createdAt);
    }

    public function testMemberships(): void
    {
        $at = new DateTimeImmutable('@1800000000');
        $membership = new Membership('s', 'l', MembershipStatus::Pending, $at);

        self::assertFalse($membership->isActive());
        $membership->activate($at->modify('+1 day'));
        self::assertTrue($membership->isActive());
        self::assertSame(1_800_086_400, $membership->changedAt->getTimestamp());
        $membership->leave($at->modify('+2 days'));
        self::assertSame(MembershipStatus::Unsubscribed, $membership->status);
        self::assertSame(1_800_172_800, $membership->changedAt->getTimestamp());
    }

    public function testSites(): void
    {
        self::assertSame('default', Site::default()->key);
        self::assertTrue(Site::of('a.b-c_1')->equals(Site::of('a.b-c_1')));
        self::assertFalse(Site::of('a')->equals(Site::of('b')));

        $this->expectException(InvalidNewsletterValue::class);
        $this->expectExceptionMessageIsOrContains('"Shop" is not a site key');
        Site::of('Shop');
    }

    private function subscriber(SubscriberStatus $status = SubscriberStatus::Pending): Subscriber
    {
        return new Subscriber('s1', Site::default(), 'ada@example.com', null, $status, new DateTimeImmutable('@1800000000'));
    }
}
