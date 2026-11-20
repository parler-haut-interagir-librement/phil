<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DunningStage;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryOffers;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySubscriptions;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * A renewal past due keeps what the offer's dunning stages say, counted from its first failure.
 */
#[CoversClass(FeatureAccess::class)]
final class DunningAccessTest extends TestCase
{
    public function testThePastDueSubscriberLosesFeaturesStageByStage(): void
    {
        $offer = Offer::create('club', 'club', 0, 'Club', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['read', 'post', 'export']);
        $offer->changeDunning([DunningStage::reduced(3, ['read']), DunningStage::cut(10)]);

        $subscriptions = new InMemorySubscriptions();
        $subscription = Subscription::start($offer, 'member-1', new DateTimeImmutable('2026-01-31 00:00 UTC'));
        $subscription->awaitPayment('R2', new DateTimeImmutable('2026-02-28 00:00 UTC'));
        $subscription->paymentFailed('R2', 5, new DateTimeImmutable('2026-02-28 00:00 UTC'));

        $subscriptions->add($subscription);
        $clock = new MockClock('2026-03-02 23:59:59 UTC');
        $access = new FeatureAccess($subscriptions, new InMemoryOffers($offer), clock: $clock);

        self::assertSame(['read', 'post', 'export'], $access->features('member-1'));

        $clock->modify('+1 second');
        $access->reset();
        self::assertSame(['read'], $access->features('member-1'));
        self::assertTrue($access->grants('member-1', 'read'));
        self::assertFalse($access->grants('member-1', 'post'));

        $clock->modify('+7 days');
        $access->reset();
        self::assertSame([], $access->features('member-1'));

        $subscription->paid('R2', BillingPeriod::of(BillingUnit::Month), $clock->now());
        $access->reset();
        self::assertSame(['read', 'post', 'export'], $access->features('member-1'));
    }

    public function testWithoutAClockTheMomentIsNow(): void
    {
        $offer = Offer::create('club', 'club', 0, 'Club', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital', ['read']);
        $offer->changeDunning([DunningStage::cut(0)]);

        $subscriptions = new InMemorySubscriptions();
        $subscription = Subscription::start($offer, 'member-1', new DateTimeImmutable('-60 days'));
        $subscription->awaitPayment('R2', new DateTimeImmutable());
        $subscription->paymentFailed('R2', 5, new DateTimeImmutable());

        $subscriptions->add($subscription);

        self::assertSame([], new FeatureAccess($subscriptions, new InMemoryOffers($offer))->features('member-1'));
    }
}
