<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Application;

use ArrayObject;
use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Order\OrderState;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Application\OfferSellables;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryOffers;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySubscriptions;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subscriptions::class)]
#[CoversClass(OfferSellables::class)]
#[CoversClass(FeatureAccess::class)]
final class SubscriptionsTest extends TestCase
{
    private InMemoryOffers $offers;

    private InMemorySubscriptions $repository;

    private Subscriptions $subscriptions;

    protected function setUp(): void
    {
        $this->offers = new InMemoryOffers(Offers::free(), Offers::pro(), Offers::team());
        $this->repository = new InMemorySubscriptions();
        $this->subscriptions = new Subscriptions($this->offers, $this->repository, new OrderModifier([new OfferSellables($this->offers, $this->repository)], [], []), 2);
    }

    public function testASubscriptionIsStartedAndKept(): void
    {
        $subscription = $this->subscriptions->subscribe('account-1', 'team', $this->at('2026-01-01'));

        self::assertSame([$subscription], $this->repository->subscriptions);
        self::assertSame(SubscriptionState::Pending, $subscription->state());
    }

    public function testAnUnknownOfferIsRefused(): void
    {
        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('No offer is known as "gold".');

        $this->subscriptions->subscribe('account-1', 'gold', $this->at('2026-01-01'));
    }

    /**
     * The order of a period is placed with one line of the offer, at its price, taxed in the zone given.
     */
    public function testThePeriodIsOrderedThenPaid(): void
    {
        $subscription = $this->subscriptions->subscribe('account-1', 'team', $this->at('2026-01-01'));

        $order = $this->subscriptions->order($subscription, '2026-0001', 'QC', $this->at('2026-01-01'));

        self::assertSame('2026-0001', $order->number);
        self::assertSame(OrderState::Placed, $order->state());
        self::assertSame('QC', $order->taxZone());
        self::assertSame(['subscription:'.$subscription->id], array_map(static fn (OrderLine $line): string => $line->reference->value(), $order->lines()));
        self::assertSame('Team', $order->lines()[0]->description);
        self::assertSame(9_990, $order->total()->minorUnits);
        self::assertSame(1, $order->lines()[0]->quantity);
        self::assertSame('2026-0001', $subscription->awaitedOrder());

        $this->subscriptions->paid($subscription, '2026-0001', $this->at('2026-01-02'));

        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertSame('2027-01-02', $subscription->periodEnd()?->format('Y-m-d'));
    }

    /**
     * A subscription not due gets no order: nothing is placed, nothing processed.
     */
    public function testAnOrderBeforeThePeriodEndsIsRefused(): void
    {
        $processed = new ArrayObject();
        $processor = new readonly class($processed) implements OrderProcessor {
            /** @param ArrayObject<int, string> $processed */
            public function __construct(private ArrayObject $processed)
            {
            }

            #[Override]
            public function process(Order $order): void
            {
                $this->processed->append($order->number);
            }
        };
        $subscriptions = new Subscriptions($this->offers, $this->repository, new OrderModifier([new OfferSellables($this->offers, $this->repository)], [], [$processor]), 2);
        $subscription = $subscriptions->subscribe('account-1', 'pro', $this->at('2026-01-01'));

        try {
            $subscriptions->order($subscription, '2026-0001', null, $this->at('2026-01-05'));
            self::fail('The order was placed.');
        } catch (SubscriptionRefused $subscriptionRefused) {
            self::assertSame(\sprintf('The subscription %s is not due for an order.', $subscription->id), $subscriptionRefused->getMessage());
        }

        self::assertCount(0, $processed);
    }

    public function testTheRenewalIsOrderedAtTheChangedTier(): void
    {
        $subscription = $this->subscriptions->subscribe('account-1', 'free', $this->at('2026-01-01'));
        $this->subscriptions->changeTo($subscription, 'pro');

        $order = $this->subscriptions->order($subscription, 'R-1', null, $this->at('2026-02-01'));

        self::assertSame(['subscription:'.$subscription->id], array_map(static fn (OrderLine $line): string => $line->reference->value(), $order->lines()));
        self::assertSame('Pro', $order->lines()[0]->description);
        self::assertNull($order->taxZone());

        $this->subscriptions->paid($subscription, 'R-1', $this->at('2026-02-01'));
        self::assertSame('pro', $subscription->offerCode());
    }

    public function testFailedPaymentsEndAfterTheConfiguredAttempts(): void
    {
        $subscription = $this->subscriptions->subscribe('account-1', 'free', $this->at('2026-01-01'));
        $this->subscriptions->changeTo($subscription, 'pro');
        $this->subscriptions->order($subscription, 'R-1', null, $this->at('2026-02-01'));

        $this->subscriptions->paymentFailed($subscription, 'R-1', $this->at('2026-02-01'));
        self::assertSame(SubscriptionState::PastDue, $subscription->state());

        $this->subscriptions->paymentFailed($subscription, 'R-1', $this->at('2026-02-03'));
        self::assertSame(SubscriptionState::Ended, $subscription->state());
    }

    public function testCancelAndResumeGoThroughTheSubscription(): void
    {
        $subscription = $this->subscriptions->subscribe('account-1', 'free', $this->at('2026-01-01'));

        $this->subscriptions->cancel($subscription, $this->at('2026-01-10'));
        self::assertTrue($subscription->cancelsAtPeriodEnd());

        $this->subscriptions->resume($subscription);
        self::assertFalse($subscription->cancelsAtPeriodEnd());
    }

    /**
     * The sweep ends what was cancelled, renews what is free, and returns what must be ordered, recent pending ones
     * included.
     */
    public function testTheSweepEndsRenewsAndReturnsWhatIsDue(): void
    {
        $at = $this->at('2026-01-01');
        $free = $this->subscriptions->subscribe('a', 'free', $at);
        $cancelled = $this->subscriptions->subscribe('b', 'free', $at);
        $this->subscriptions->cancel($cancelled, $at);
        $trial = $this->subscriptions->subscribe('c', 'pro', $at);
        $pending = $this->subscriptions->subscribe('d', 'team', $this->at('2026-01-28'));
        $upgraded = $this->subscriptions->subscribe('e', 'free', $at);
        $this->subscriptions->changeTo($upgraded, 'pro');
        $awaiting = $this->subscriptions->subscribe('f', 'team', $at);
        $this->subscriptions->order($awaiting, 'O-1', null, $at);

        $due = $this->subscriptions->sweep($this->at('2026-02-01'));

        self::assertSame([$trial->id, $upgraded->id, $pending->id], array_map(static fn (Subscription $subscription): string => $subscription->id, $due));
        self::assertSame('2026-03-01', $free->periodEnd()?->format('Y-m-d'));
        self::assertSame(SubscriptionState::Ended, $cancelled->state());
        self::assertSame('free', $upgraded->offerCode());
    }

    /**
     * Changed to the free tier during its trial: when the trial ends, the sweep starts the free tier and goes on
     * with the others.
     */
    public function testTheSweepStartsAFreeTierAtTheEndOfATrial(): void
    {
        $at = $this->at('2026-01-01');
        $downgraded = $this->subscriptions->subscribe('a', 'pro', $at);
        $this->subscriptions->changeTo($downgraded, 'free');
        $trial = $this->subscriptions->subscribe('b', 'pro', $at);

        $due = $this->subscriptions->sweep($this->at('2026-01-20'));

        self::assertSame([$trial], $due);
        self::assertSame(SubscriptionState::Active, $downgraded->state());
        self::assertSame('free', $downgraded->offerCode());
    }

    /**
     * One subscription the sweep cannot handle — its offer is gone — is handed over and skipped; the others go on.
     */
    public function testOneRefusalDoesNotStopTheSweep(): void
    {
        $at = $this->at('2026-01-01');
        $orphan = $this->subscriptions->subscribe('a', 'pro', $at);
        $this->subscriptions->changeTo($orphan, 'team');
        $this->offers->remove('team');
        $trial = $this->subscriptions->subscribe('b', 'pro', $at);
        $refused = [];

        $due = $this->subscriptions->sweep($this->at('2026-01-20'), static function (Subscription $subscription, SubscriptionRefused $refusal) use (&$refused): void {
            $refused[] = [$subscription->id, $refusal->getMessage()];
        });

        self::assertSame([$trial], $due);
        self::assertSame([[$orphan->id, 'No offer is known as "team".']], $refused);
        self::assertSame([$trial], $this->subscriptions->sweep($this->at('2026-01-20')));
    }

    /**
     * A sign-up whose first period was never paid ends after the configured days, and is never read again.
     */
    public function testPendingSubscriptionsAreAbandonedAfterTheConfiguredDays(): void
    {
        $subscriptions = new Subscriptions($this->offers, $this->repository, new OrderModifier([new OfferSellables($this->offers, $this->repository)], [], []), 2, 3);
        $failed = $subscriptions->subscribe('a', 'team', $this->at('2026-01-01'));
        $subscriptions->order($failed, 'O-1', null, $this->at('2026-01-01'));
        $subscriptions->paymentFailed($failed, 'O-1', $this->at('2026-01-01'));

        $recent = $subscriptions->subscribe('b', 'team', $this->at('2026-01-03'));

        self::assertSame([$recent], $subscriptions->sweep(new DateTimeImmutable('2026-01-04 12:00 UTC')));
        self::assertSame(SubscriptionState::Ended, $failed->state());
        self::assertSame(SubscriptionState::Pending, $recent->state());
        self::assertSame([$recent], $this->repository->pendingCreatedBefore($this->at('2026-01-04')));
    }

    /**
     * Without configured days, a sign-up is abandoned once seven days have passed since it was made, not before.
     */
    public function testPendingSubscriptionsAreAbandonedAfterSevenDaysByDefault(): void
    {
        $subscription = $this->subscriptions->subscribe('a', 'team', $this->at('2026-01-01'));

        $this->subscriptions->sweep($this->at('2026-01-08'));
        self::assertSame(SubscriptionState::Pending, $subscription->state());

        $this->subscriptions->sweep(new DateTimeImmutable('2026-01-08 00:00:01 UTC'));
        self::assertSame(SubscriptionState::Ended, $subscription->state());
    }

    /**
     * A period over whose order awaits its payment is neither renewed nor ordered again by the sweep.
     */
    public function testTheSweepSkipsAPeriodWhoseOrderAwaitsItsPayment(): void
    {
        $trial = $this->subscriptions->subscribe('a', 'pro', $this->at('2026-01-01'));
        $this->subscriptions->order($trial, 'R-1', null, $this->at('2026-01-20'));

        self::assertSame([], $this->subscriptions->sweep($this->at('2026-01-20')));
        self::assertSame(SubscriptionState::Trialing, $trial->state());
        self::assertSame('R-1', $trial->awaitedOrder());
    }

    /**
     * Each change made here — a sign-up, a payment, a refused payment, a cancellation, a sweep — has the subscriber's
     * features read again.
     */
    public function testEveryChangeHasTheFeaturesReadAgain(): void
    {
        $access = new FeatureAccess($this->repository, $this->offers);
        $subscriptions = new Subscriptions($this->offers, $this->repository, new OrderModifier([new OfferSellables($this->offers, $this->repository)], [], []), 2, access: $access);
        $reads = function (string $subscriber) use ($access): int {
            $access->features($subscriber);
            $access->features($subscriber);

            return $this->repository->reads[$subscriber];
        };

        self::assertSame(1, $reads('a'));
        $paid = $subscriptions->subscribe('a', 'team', $this->at('2026-01-01'));
        self::assertSame(2, $reads('a'));
        $subscriptions->order($paid, 'O-1', null, $this->at('2026-01-01'));
        self::assertSame(2, $reads('a'), 'an order changes no access');
        $subscriptions->paid($paid, 'O-1', $this->at('2026-01-01'));
        self::assertSame(3, $reads('a'));
        $subscriptions->cancel($paid, $this->at('2026-01-02'));
        self::assertSame(4, $reads('a'));

        $failed = $subscriptions->subscribe('b', 'team', $this->at('2026-01-01'));
        $subscriptions->order($failed, 'O-2', null, $this->at('2026-01-01'));
        self::assertSame(1, $reads('b'));
        $subscriptions->paymentFailed($failed, 'O-2', $this->at('2026-01-01'));
        self::assertSame(2, $reads('b'));
        $subscriptions->sweep($this->at('2026-01-09'));
        self::assertSame(SubscriptionState::Ended, $failed->state());
        self::assertSame(3, $reads('b'));
    }

    public function testTheSweepReadsInBatches(): void
    {
        $at = $this->at('2026-01-01');

        foreach (['a', 'b', 'c'] as $subscriber) {
            $this->subscriptions->subscribe($subscriber, 'pro', $at);
        }

        self::assertCount(2, $this->subscriptions->sweep($this->at('2026-01-20'), limit: 2));
    }

    /**
     * A subscriber's features are read once, and again once a change of their subscription is made here.
     */
    public function testFeaturesAreReadOnceUntilTheSubscriptionChanges(): void
    {
        $access = new FeatureAccess($this->repository, $this->offers);
        $subscriptions = new Subscriptions($this->offers, $this->repository, new OrderModifier([new OfferSellables($this->offers, $this->repository)], [], []), 2, access: $access);
        $free = $subscriptions->subscribe('account-1', 'free', $this->at('2026-01-01'));

        self::assertSame(['projects:3'], $access->features('account-1'));
        self::assertTrue($access->grants('account-1', 'projects:3'));
        self::assertSame(['account-1' => 1], $this->repository->reads);

        $subscriptions->cancel($free, $this->at('2026-01-02'));
        $subscriptions->subscribe('account-1', 'team', $this->at('2026-01-02'));
        self::assertSame(['projects:3'], $access->features('account-1'));
        self::assertSame(['account-1' => 2], $this->repository->reads);

        $this->repository->subscriptions = [];
        self::assertSame(['projects:3'], $access->features('account-1'));
        $access->reset();
        self::assertSame([], $access->features('account-1'));
    }

    public function testFeaturesComeFromTheSubscriptionsGrantingAccess(): void
    {
        $at = $this->at('2026-01-01');
        $this->subscriptions->subscribe('account-1', 'free', $at);
        $this->subscriptions->subscribe('account-1', 'pro', $at);
        $this->subscriptions->subscribe('account-1', 'team', $at);

        $access = new FeatureAccess($this->repository, $this->offers);

        self::assertSame(['projects:3', 'projects:unlimited', 'export'], $access->features('account-1'));
        self::assertTrue($access->grants('account-1', 'export'));
        self::assertFalse($access->grants('account-1', 'seats'));
        self::assertSame([], $access->features('account-2'));
    }

    /**
     * The offers of every subscription granting access are read together, once, and an offer gone from the store
     * grants nothing.
     */
    public function testTheOffersAreReadInOneBatch(): void
    {
        $at = $this->at('2026-01-01');
        $this->subscriptions->subscribe('account-1', 'free', $at);
        $this->subscriptions->subscribe('account-1', 'pro', $at);
        $this->subscriptions->subscribe('account-1', 'team', $at);

        $this->offers->remove('free');
        $this->offers->batches = [];

        $access = new FeatureAccess($this->repository, $this->offers);

        self::assertSame(['projects:unlimited', 'export'], $access->features('account-1'));
        self::assertSame([['free', 'pro']], $this->offers->batches, 'the pending team subscription grants nothing yet');
    }

    public function testAnOfferIsSoldAsAnOfferReference(): void
    {
        $sellables = new OfferSellables($this->offers, $this->repository);

        self::assertTrue($sellables->supports('offer'));
        self::assertTrue($sellables->supports('subscription'));
        self::assertFalse($sellables->supports('product'));

        $sellable = $sellables->resolve(SellableReference::of('offer', 'pro'));
        self::assertNotNull($sellable);
        self::assertSame('Pro', $sellable->description);
        self::assertSame(999, $sellable->unitPrice->minorUnits);
        self::assertSame('digital', $sellable->taxCategory);
        self::assertNull($sellables->resolve(SellableReference::of('offer', 'gold')));
    }

    /**
     * A withdrawn offer sells to no cart, but keeps renewing the subscriptions it has.
     */
    public function testAWithdrawnOfferOnlyRenewsItsSubscriptions(): void
    {
        $sellables = new OfferSellables($this->offers, $this->repository);
        $subscription = $this->subscriptions->subscribe('account-1', 'team', $this->at('2026-01-01'));
        $this->offers->find('team')?->withdraw();

        self::assertNull($sellables->resolve(SellableReference::of('offer', 'team')));
        self::assertSame('Team', $sellables->resolve(SellableReference::of('subscription', $subscription->id))?->description);

        $order = $this->subscriptions->order($subscription, 'O-1', null, $this->at('2026-01-01'));
        self::assertSame(9_990, $order->total()->minorUnits);

        self::assertNull($sellables->resolve(SellableReference::of('subscription', $subscription->id)));
        self::assertNull($sellables->resolve(SellableReference::of('subscription', 'unknown')));
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 00:00 UTC');
    }
}
