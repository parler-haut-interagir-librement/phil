<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subscription::class)]
#[CoversClass(SubscriptionRefused::class)]
final class SubscriptionTest extends TestCase
{
    public function testAPaidOfferWithoutTrialWaitsForItsFirstPayment(): void
    {
        $at = $this->at('2026-01-31');
        $subscription = Subscription::start(Offers::team(), 'account-1', $at);

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $subscription->id);
        self::assertSame('account-1', $subscription->subscriber);
        self::assertSame('studio', $subscription->family);
        self::assertSame($at, $subscription->createdAt);
        self::assertSame('team', $subscription->offerCode());
        self::assertSame('team', $subscription->renewalOfferCode());
        self::assertSame(SubscriptionState::Pending, $subscription->state());
        self::assertNull($subscription->periodEnd());
        self::assertSame(0, $subscription->periodsPaid());
        self::assertFalse($subscription->grantsAccess());
        self::assertTrue($subscription->isDue($at));
        self::assertSame(1, $subscription->version());
        self::assertNull($subscription->endedAt());
        self::assertFalse($subscription->cancelsAtPeriodEnd());

        $subscription->awaitPayment('2026-0001', $at);
        self::assertSame('2026-0001', $subscription->awaitedOrder());
        self::assertFalse($subscription->isDue($at));

        $subscription->paid('2026-0001', BillingPeriod::of(BillingUnit::Year), $this->at('2026-02-01'));

        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertSame('2027-02-01', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(1, $subscription->periodsPaid());
        self::assertNull($subscription->awaitedOrder());
        self::assertTrue($subscription->grantsAccess());
        self::assertFalse($subscription->isDue($this->at('2027-01-31')));
        self::assertTrue($subscription->isDue($this->at('2027-02-01')));
    }

    public function testAFreeOfferStartsActive(): void
    {
        $subscription = Subscription::start(Offers::free(), 'account-1', $this->at('2026-01-31'));

        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertSame('2026-02-28', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(1, $subscription->periodsPaid());

        $subscription->renewFree(BillingPeriod::of(BillingUnit::Month), $this->at('2026-02-28'));

        self::assertSame('2026-03-31', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(2, $subscription->periodsPaid());
    }

    /**
     * Paid during the trial, the first period starts when the trial ends; paid after, it starts at the payment.
     */
    public function testATrialGivesAccessUntilItEnds(): void
    {
        $subscription = Subscription::start(Offers::pro(), 'account-1', $this->at('2026-01-01'));

        self::assertSame(SubscriptionState::Trialing, $subscription->state());
        self::assertSame('2026-01-15', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertTrue($subscription->grantsAccess());
        self::assertFalse($subscription->isDue($this->at('2026-01-14')));
        self::assertTrue($subscription->isDue($this->at('2026-01-15')));

        $early = Subscription::start(Offers::pro(), 'account-1', $this->at('2026-01-01'));
        $early->awaitPayment('A', $this->at('2026-01-15'));
        $early->paid('A', BillingPeriod::of(BillingUnit::Month), $this->at('2026-01-10'));
        self::assertSame('2026-02-15', $early->periodEnd()?->format('Y-m-d'));

        $subscription->awaitPayment('B', $this->at('2026-01-15'));
        $subscription->paid('B', BillingPeriod::of(BillingUnit::Month), $this->at('2026-01-20'));
        self::assertSame('2026-02-20', $subscription->periodEnd()?->format('Y-m-d'));
    }

    /**
     * Renewals add a period to the anchor: a payment a few days late does not move the next end.
     */
    public function testRenewalsCountFromTheAnchor(): void
    {
        $subscription = $this->active();

        $subscription->awaitPayment('R2', $this->at('2026-02-28'));
        $subscription->paid('R2', BillingPeriod::of(BillingUnit::Month), $this->at('2026-03-03'));

        self::assertSame('2026-03-31', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(2, $subscription->periodsPaid());
    }

    public function testFailedRenewalsEndAfterTheLastAttempt(): void
    {
        $subscription = $this->active();
        $subscription->awaitPayment('R2', $this->at('2026-02-28'));

        $subscription->paymentFailed('R2', 3, $this->at('2026-02-28'));
        self::assertSame(SubscriptionState::PastDue, $subscription->state());
        self::assertSame(1, $subscription->failedPayments());
        self::assertTrue($subscription->grantsAccess());
        self::assertFalse($subscription->isDue($this->at('2026-03-01')));

        $subscription->paymentFailed('R2', 3, $this->at('2026-03-02'));
        self::assertSame(SubscriptionState::PastDue, $subscription->state());

        $subscription->paymentFailed('R2', 3, $this->at('2026-03-05'));
        self::assertSame(SubscriptionState::Ended, $subscription->state());
        self::assertSame('2026-03-05', $subscription->endedAt()?->format('Y-m-d'));
        self::assertNull($subscription->awaitedOrder());
        self::assertFalse($subscription->grantsAccess());
    }

    public function testARetryThatSucceedsRestoresTheSubscription(): void
    {
        $subscription = $this->active();
        $subscription->awaitPayment('R2', $this->at('2026-02-28'));
        $subscription->paymentFailed('R2', 3, $this->at('2026-02-28'));

        $subscription->paid('R2', BillingPeriod::of(BillingUnit::Month), $this->at('2026-03-02'));

        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertSame(0, $subscription->failedPayments());
        self::assertSame('2026-03-31', $subscription->periodEnd()?->format('Y-m-d'));
    }

    public function testAFirstPaymentThatFailsWaitsForAnotherTry(): void
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', $this->at('2026-01-01'));
        $subscription->awaitPayment('F', $this->at('2026-01-01'));

        $subscription->paymentFailed('F', 1, $this->at('2026-01-01'));

        self::assertSame(SubscriptionState::Pending, $subscription->state());
        self::assertSame(1, $subscription->failedPayments());
        self::assertSame('F', $subscription->awaitedOrder());
    }

    public function testAPaymentOfAnotherOrderIsRefused(): void
    {
        $subscription = $this->active();
        $subscription->awaitPayment('R2', $this->at('2026-02-28'));

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The subscription %s awaits no payment of the order R9.', $subscription->id));

        $subscription->paid('R9', BillingPeriod::of(BillingUnit::Month), $this->at('2026-03-01'));
    }

    public function testAFailureOfAnotherOrderIsRefused(): void
    {
        $this->expectException(SubscriptionRefused::class);

        $this->active()->paymentFailed('R9', 3, $this->at('2026-03-01'));
    }

    public function testAnOrderBeforeThePeriodEndsIsRefused(): void
    {
        $subscription = $this->active();

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The subscription %s is not due for an order.', $subscription->id));

        $subscription->awaitPayment('R2', $this->at('2026-02-27'));
    }

    public function testCancellingKeepsThePaidPeriod(): void
    {
        $subscription = $this->active();
        $subscription->cancel($this->at('2026-02-10'));

        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertTrue($subscription->cancelsAtPeriodEnd());
        self::assertTrue($subscription->grantsAccess());
        self::assertFalse($subscription->isDue($this->at('2026-03-01')));
        self::assertFalse($subscription->expire($this->at('2026-02-27')));
        self::assertTrue($subscription->expire($this->at('2026-02-28')));
        self::assertSame(SubscriptionState::Ended, $subscription->state());
        self::assertFalse($subscription->cancelsAtPeriodEnd());
        self::assertFalse($subscription->expire($this->at('2026-03-01')));
    }

    public function testACancellationIsTakenBackBeforeThePeriodEnds(): void
    {
        $subscription = $this->active();
        $subscription->cancel($this->at('2026-02-10'));
        $subscription->resume();

        self::assertFalse($subscription->cancelsAtPeriodEnd());
        self::assertFalse($subscription->expire($this->at('2026-03-01')));

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The subscription %s is active: it cannot be resumed.', $subscription->id));

        $subscription->resume();
    }

    public function testAPendingOrPastDueSubscriptionEndsAtOnce(): void
    {
        $pending = Subscription::start(Offers::team(), 'account-1', $this->at('2026-01-01'));
        $pending->cancel($this->at('2026-01-02'));
        self::assertSame(SubscriptionState::Ended, $pending->state());
        self::assertSame('2026-01-02', $pending->endedAt()?->format('Y-m-d'));

        $pastDue = $this->active();
        $pastDue->awaitPayment('R2', $this->at('2026-02-28'));
        $pastDue->paymentFailed('R2', 3, $this->at('2026-02-28'));
        $pastDue->cancel($this->at('2026-03-01'));
        self::assertSame(SubscriptionState::Ended, $pastDue->state());

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The subscription %s is ended: it cannot be cancelled.', $pending->id));

        $pending->cancel($this->at('2026-01-03'));
    }

    public function testAnEndedSubscriptionIsNotResumed(): void
    {
        $subscription = $this->active();
        $subscription->cancel($this->at('2026-02-10'));
        $subscription->expire($this->at('2026-02-28'));

        $this->expectException(SubscriptionRefused::class);

        $subscription->resume();
    }

    /**
     * A change of tier takes effect on the order of the next period; a tier of another period starts where the last
     * period paid ends.
     */
    public function testAChangeOfTierWaitsForTheNextPeriod(): void
    {
        $subscription = $this->active();
        $subscription->changeTo(Offers::team());

        self::assertSame('free', $subscription->offerCode());
        self::assertSame('team', $subscription->renewalOfferCode());

        $subscription->changeTo(Offers::free());
        self::assertSame('free', $subscription->renewalOfferCode());

        $subscription->changeTo(Offers::team());
        $subscription->awaitPayment('R2', $this->at('2026-02-28'));
        $subscription->paid('R2', BillingPeriod::of(BillingUnit::Year), $this->at('2026-02-28'));

        self::assertSame('team', $subscription->offerCode());
        self::assertSame('team', $subscription->renewalOfferCode());
        self::assertSame('2027-02-28', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(2, $subscription->periodsPaid());
    }

    public function testAFreeRenewalTakesTheChangedTier(): void
    {
        $subscription = Subscription::start(Offers::free(), 'account-1', $this->at('2026-01-31'));
        $other = Offer::create('basic', 'studio', 0, 'Basic', Money::zero(Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital');
        $subscription->changeTo($other);

        $subscription->renewFree(BillingPeriod::of(BillingUnit::Month), $this->at('2026-02-28'));

        self::assertSame('basic', $subscription->offerCode());
        self::assertSame('basic', $subscription->renewalOfferCode());
    }

    public function testATierOfAnotherFamilyIsRefused(): void
    {
        $other = Offer::create('gold', 'patrons', 1, 'Gold', Money::of(500, Currency::of('CAD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital');

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('The offer "gold" is not of the family "studio": a subscription changes tier within its family.');

        $this->active()->changeTo($other);
    }

    public function testAWithdrawnTierIsRefused(): void
    {
        $team = Offers::team();
        $team->withdraw();

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('The offer "team" is withdrawn: nobody subscribes to it any more.');

        $this->active()->changeTo($team);
    }

    public function testNoChangeWhileAPaymentIsAwaited(): void
    {
        $subscription = $this->active();
        $subscription->awaitPayment('R2', $this->at('2026-02-28'));

        $this->expectException(SubscriptionRefused::class);

        $subscription->changeTo(Offers::team());
    }

    public function testNoChangeOnceEnded(): void
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', $this->at('2026-01-01'));
        $subscription->cancel($this->at('2026-01-01'));

        $this->expectException(SubscriptionRefused::class);

        $subscription->changeTo(Offers::pro());
    }

    public function testAWithdrawnOfferIsNotSubscribedTo(): void
    {
        $team = Offers::team();
        $team->withdraw();

        $this->expectException(SubscriptionRefused::class);

        Subscription::start($team, 'account-1', $this->at('2026-01-01'));
    }

    public function testASubscriberIsNamed(): void
    {
        self::assertSame(255, \strlen(Subscription::start(Offers::free(), str_repeat('s', 255), $this->at('2026-01-01'))->subscriber));

        foreach ([' ', str_repeat('s', 256)] as $subscriber) {
            try {
                Subscription::start(Offers::free(), $subscriber, $this->at('2026-01-01'));
                self::fail('The subscriber was accepted.');
            } catch (InvalidValue $invalidValue) {
                self::assertSame('A subscriber is named by 1 to 255 characters.', $invalidValue->getMessage());
            }
        }
    }

    public function testAFreeRenewalIsRefusedBeforeThePeriodEnds(): void
    {
        $this->expectException(SubscriptionRefused::class);

        Subscription::start(Offers::free(), 'account-1', $this->at('2026-01-31'))->renewFree(BillingPeriod::of(BillingUnit::Month), $this->at('2026-02-27'));
    }

    public function testAFreeRenewalOfATrialStillRunningIsRefused(): void
    {
        $this->expectException(SubscriptionRefused::class);

        Subscription::start(Offers::pro(), 'account-1', $this->at('2026-01-01'))->renewFree(BillingPeriod::of(BillingUnit::Month), $this->at('2026-01-10'));
    }

    /**
     * The subscriber changed to the free tier during the trial: when the trial ends, the free tier starts.
     */
    public function testATrialEndingOnAFreeTierBecomesActive(): void
    {
        $subscription = Subscription::start(Offers::pro(), 'account-1', $this->at('2026-01-01'));
        $subscription->changeTo(Offers::free());

        $subscription->renewFree(BillingPeriod::of(BillingUnit::Month), $this->at('2026-01-20'));

        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertSame('free', $subscription->offerCode());
        self::assertSame('2026-02-20', $subscription->periodEnd()?->format('Y-m-d'));
    }

    public function testAPendingSubscriptionIsAbandoned(): void
    {
        $pending = Subscription::start(Offers::team(), 'account-1', $this->at('2026-01-01'));
        $pending->abandon($this->at('2026-01-09'));

        self::assertSame(SubscriptionState::Ended, $pending->state());
        self::assertSame('2026-01-09', $pending->endedAt()?->format('Y-m-d'));

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('cannot be abandoned');

        $this->active()->abandon($this->at('2026-01-09'));
    }

    /**
     * Cancelled while the renewal's payment was in flight: the payment decides. Paid, the period is granted and the
     * subscription ends at its end.
     */
    public function testACancellationWaitsForTheRenewalPaymentInFlight(): void
    {
        $subscription = $this->active();
        $subscription->awaitPayment('R2', $this->at('2026-02-28'));
        $subscription->cancel($this->at('2026-03-01'));

        self::assertFalse($subscription->expire($this->at('2026-03-02')));
        self::assertSame('R2', $subscription->awaitedOrder());

        $subscription->paid('R2', BillingPeriod::of(BillingUnit::Month), $this->at('2026-03-03'));
        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertTrue($subscription->cancelsAtPeriodEnd());

        $end = $subscription->periodEnd();
        self::assertNotNull($end);
        self::assertTrue($subscription->expire($end));
        self::assertSame(SubscriptionState::Ended, $subscription->state());
    }

    /**
     * Failed, the subscription ends at once: nobody wants the period retried.
     */
    public function testACancelledSubscriptionEndsWhenItsRenewalPaymentFails(): void
    {
        $subscription = $this->active();
        $subscription->awaitPayment('R2', $this->at('2026-02-28'));
        $subscription->cancel($this->at('2026-03-01'));

        $subscription->paymentFailed('R2', 3, $this->at('2026-03-02'));

        self::assertSame(SubscriptionState::Ended, $subscription->state());
        self::assertNull($subscription->awaitedOrder());
    }

    public function testOnlyASubscriptionThatMayBeOrderedAwaitsAnOrder(): void
    {
        self::assertTrue($this->active()->awaitsAnOrder());

        $awaiting = $this->active();
        $awaiting->awaitPayment('R2', $this->at('2026-02-28'));
        self::assertFalse($awaiting->awaitsAnOrder());

        $cancelled = $this->active();
        $cancelled->cancel($this->at('2026-02-01'));
        self::assertFalse($cancelled->awaitsAnOrder());

        $ended = Subscription::start(Offers::team(), 'account-1', $this->at('2026-01-01'));
        $ended->abandon($this->at('2026-01-02'));
        self::assertFalse($ended->awaitsAnOrder());
    }

    public function testAnEndedOrCancelledSubscriptionIsNeverDue(): void
    {
        $subscription = $this->active();
        $subscription->cancel($this->at('2026-02-01'));
        self::assertFalse($subscription->isDue($this->at('2026-04-01')));

        $pastDue = $this->active();
        $pastDue->awaitPayment('R2', $this->at('2026-02-28'));
        $pastDue->paymentFailed('R2', 1, $this->at('2026-02-28'));
        self::assertFalse($pastDue->isDue($this->at('2026-04-01')));
    }

    public function testRefusalsAreDomainErrors(): void
    {
        self::assertInstanceOf(DomainError::class, SubscriptionRefused::unknownOffer('x'));
        self::assertSame('No offer is known as "x".', SubscriptionRefused::unknownOffer('x')->getMessage());
    }

    /**
     * A free subscription, active from January 31st: its periods end on the last day of each month.
     */
    private function active(): Subscription
    {
        return Subscription::start(Offers::free(), 'account-1', $this->at('2026-01-31'));
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 00:00 UTC');
    }
}
