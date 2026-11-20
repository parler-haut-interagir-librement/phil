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
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\Offers;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Changes of tier at once and gifts, on the subscription itself.
 */
#[CoversClass(Subscription::class)]
#[CoversClass(SubscriptionRefused::class)]
final class SubscriptionChangeTest extends TestCase
{
    public function testAChangeComputesTheCreditFromWhatWasPaid(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));

        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-04-11'));

        $change = $subscription->pendingChange();
        self::assertNotNull($change);
        self::assertSame('premium', $change->offerCode);
        self::assertSame('2026-04-11', $change->at->format('Y-m-d'));
        self::assertSame(20 * 86_400, $change->secondsLeft);
        self::assertSame(30 * 86_400, $change->periodSeconds);
        self::assertSame(667, $change->credit->minorUnits);
        self::assertSame('premium', $subscription->renewalOfferCode());
        self::assertTrue($subscription->awaitsAnOrder(), 'its order may be placed');

        $subscription->awaitChangePayment('C-1', $this->cad(3_000), $this->cad(5_000));
        self::assertSame('C-1', $subscription->awaitedOrder());
        self::assertFalse($subscription->awaitsAnOrder());

        $subscription->paid('C-1', BillingPeriod::of(BillingUnit::Month), $this->at('2026-04-11'));
        self::assertSame(0, $subscription->carriedCredit()->minorUnits, 'the credit taken off is at most what there was');
    }

    public function testNoChangeIsPendingByDefault(): void
    {
        $subscription = Subscription::start(Offers::team(), 'account-1', $this->at('2026-04-01'));

        self::assertNull($subscription->pendingChange());
        self::assertNull($subscription->periodStart());
        self::assertNull($subscription->payer());
        self::assertSame('CAD', $subscription->currency()->code);
        self::assertTrue($subscription->periodAmount()->isZero());
        self::assertTrue($subscription->carriedCredit()->isZero());
    }

    /**
     * A period paid before the trial it follows ended starts later: changed before it starts, it is credited whole.
     */
    public function testAPeriodNotStartedIsCreditedWhole(): void
    {
        $offer = Offer::create('trial', 'plans', 1, 'Trial', $this->cad(1_000), BillingPeriod::of(BillingUnit::Month), 14, 'digital');
        $subscription = Subscription::start($offer, 'account-1', $this->at('2026-04-01'));
        $subscription->awaitPayment('F-1', $this->at('2026-04-15'), $this->cad(1_000));
        $subscription->paid('F-1', $offer->period, $this->at('2026-04-10'));
        self::assertSame('2026-04-15', $subscription->periodStart()?->format('Y-m-d'));

        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-04-12'));

        self::assertSame(1_000, $subscription->pendingChange()?->credit->minorUnits);
        self::assertSame($subscription->pendingChange()->periodSeconds, $subscription->pendingChange()->secondsLeft);
    }

    public function testAChangeOutsideTheFamilyOrCurrencyOrSaleIsRefused(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));
        $withdrawn = $this->monthly('old', 2, 2_000);
        $withdrawn->withdraw();

        $cases = [
            [Offer::create('other', 'elsewhere', 2, 'Other', $this->cad(2_000), BillingPeriod::of(BillingUnit::Month), 0, 'digital'), 'The offer "other" is not of the family "plans": a subscription changes tier within its family.'],
            [$withdrawn, 'The offer "old" is withdrawn: nobody subscribes to it any more.'],
            [Offer::create('usd', 'plans', 2, 'USD', Money::of(2_000, Currency::of('USD')), BillingPeriod::of(BillingUnit::Month), 0, 'digital'), 'The offer "usd" is not priced in CAD, the currency of the subscription.'],
        ];

        foreach ($cases as [$offer, $message]) {
            foreach (['startImmediateChange', 'changeTo', 'switchDuringTrial'] as $method) {
                try {
                    match ($method) {
                        'startImmediateChange' => $subscription->startImmediateChange($offer, $this->at('2026-04-11')),
                        'changeTo' => $subscription->changeTo($offer),
                        'switchDuringTrial' => $subscription->switchDuringTrial($offer),
                    };
                    self::fail($method.' took '.$offer->code);
                } catch (SubscriptionRefused $subscriptionRefused) {
                    self::assertSame($message, $subscriptionRefused->getMessage());
                }
            }
        }
    }

    public function testAChangeOnceThePeriodIsOverIsRefused(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The period of the subscription %s is over: it changes tier with its renewal.', $subscription->id));

        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-05-01'));
    }

    public function testAChangeToTheSameTierIsRefused(): void
    {
        $basic = $this->monthly('basic', 1, 1_000);
        $subscription = $this->active($basic);

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('is active: it cannot change tier at once unless active');

        $subscription->startImmediateChange($basic, $this->at('2026-04-11'));
    }

    public function testATrialDoesNotChangeAtOnceWithAnOrder(): void
    {
        $subscription = Subscription::start(Offers::pro(), 'account-1', $this->at('2026-04-01'));

        $this->expectException(SubscriptionRefused::class);

        $subscription->startImmediateChange(Offers::team(), $this->at('2026-04-02'));
    }

    public function testOnlyATrialSwitches(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('it cannot switch tier as a trial');

        $subscription->switchDuringTrial($this->monthly('premium', 2, 3_000));
    }

    public function testATrialAwaitingItsFirstPaymentDoesNotSwitch(): void
    {
        $subscription = Subscription::start(Offers::pro(), 'account-1', $this->at('2026-04-01'));
        $subscription->awaitPayment('F-1', $this->at('2026-04-15'));

        $this->expectException(SubscriptionRefused::class);

        $subscription->switchDuringTrial(Offers::team());
    }

    public function testAChangeIsAwaitedOnlyOnceStarted(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));

        try {
            $subscription->awaitChangePayment('C-1', $this->cad(3_000), $this->cad(0));
            self::fail('A change not started was awaited.');
        } catch (SubscriptionRefused $subscriptionRefused) {
            self::assertStringEndsWith('it cannot await the payment of a change it has not started.', $subscriptionRefused->getMessage());
        }

        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-04-11'));
        $subscription->awaitChangePayment('C-1', $this->cad(3_000), $this->cad(0));

        try {
            $subscription->awaitChangePayment('C-2', $this->cad(3_000), $this->cad(0));
            self::fail('A second order of the change was awaited.');
        } catch (SubscriptionRefused) {
        }

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('it cannot give up a change whose order awaits its payment.');

        $subscription->abandonChange();
    }

    public function testAnEndedSubscriptionForgetsItsChange(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));
        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-04-11'));
        $subscription->cancel($this->at('2026-04-11'));
        $subscription->expire($this->at('2026-05-01'));

        self::assertSame(SubscriptionState::Ended, $subscription->state());
        self::assertNull($subscription->pendingChange());
        self::assertSame('basic', $subscription->renewalOfferCode());
    }

    public function testAmountsAreInTheSubscriptionsCurrencyAndNotNegative(): void
    {
        foreach ([Money::of(1, Currency::of('USD')), $this->cad(-1)] as $amount) {
            try {
                Subscription::start(Offers::team(), 'account-1', $this->at('2026-04-01'))->awaitPayment('F-1', $this->at('2026-04-01'), $amount);
                self::fail('The amount was kept.');
            } catch (InvalidValue $invalidValue) {
                self::assertStringEndsWith('keeps amounts of zero or more in CAD.', $invalidValue->getMessage());
            }
        }
    }

    /**
     * A renewal takes off at most the credit carried over.
     */
    public function testARenewalTakesOffAtMostTheCarriedCredit(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));
        $subscription->awaitPayment('R-1', $this->at('2026-05-01'), $this->cad(1_000), $this->cad(500));
        $subscription->paid('R-1', BillingPeriod::of(BillingUnit::Month), $this->at('2026-05-01'));

        self::assertSame(0, $subscription->carriedCredit()->minorUnits);
    }

    public function testAFreeRenewalIsWorthNothing(): void
    {
        $basic = $this->monthly('basic', 1, 1_000);
        $free = Offer::create('none', 'plans', 0, 'None', $this->cad(0), BillingPeriod::of(BillingUnit::Month), 0, 'digital');
        $subscription = $this->active($basic);
        self::assertSame(1_000, $subscription->periodAmount()->minorUnits);
        $subscription->changeTo($free);

        $subscription->renewFree($free->period, $this->at('2026-05-01'));

        self::assertTrue($subscription->periodAmount()->isZero());
    }

    public function testAGiftStartsActiveForThePeriodsPaid(): void
    {
        $at = $this->at('2026-01-31');
        $subscription = Subscription::fromGift(Offers::pro(), StableIdentifier::of('friend-1', 'beneficiary'), 'buyer-1', 2, $this->cad(999), $at);

        self::assertSame('friend-1', $subscription->subscriber);
        self::assertSame('buyer-1', $subscription->payer());
        self::assertSame('pro', $subscription->offerCode());
        self::assertSame('studio', $subscription->family);
        self::assertSame($at, $subscription->createdAt);
        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertSame('2026-03-31', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame('2026-02-28', $subscription->periodStart()?->format('Y-m-d'));
        self::assertSame(2, $subscription->periodsPaid());
        self::assertSame(999, $subscription->periodAmount()->minorUnits);
        self::assertFalse($subscription->cancelsAtPeriodEnd());
        self::assertTrue($subscription->isDue($this->at('2026-03-31')));

        $ending = Subscription::fromGift(Offers::pro(), StableIdentifier::of('friend-1', 'beneficiary'), null, 36, $this->cad(999), $at);
        self::assertTrue($ending->cancelsAtPeriodEnd());
        self::assertSame('2029-01-31', $ending->periodEnd()?->format('Y-m-d'));

        foreach ([0, 37] as $periods) {
            try {
                Subscription::fromGift(Offers::pro(), StableIdentifier::of('friend-1', 'beneficiary'), null, $periods, $this->cad(999), $at);
                self::fail(\sprintf('A gift of %d periods started.', $periods));
            } catch (InvalidValue $invalidValue) {
                self::assertSame(\sprintf('A gift pays 1 to 36 periods; %d does not.', $periods), $invalidValue->getMessage());
            }
        }
    }

    /**
     * A renewal ordered while a change waits for the next renewal sells the tier changed to at once.
     */
    public function testTheChangeAtOnceComesBeforeTheChangeAtRenewal(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));
        $subscription->changeTo($this->monthly('premium', 2, 3_000));

        $subscription->startImmediateChange($this->monthly('annual', 3, 30_000), $this->at('2026-04-11'));

        self::assertSame('annual', $subscription->renewalOfferCode());
        self::assertSame('annual', $subscription->pendingChange()?->offerCode);
    }

    public function testACancelledActiveSubscriptionDoesNotChangeAtOnce(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));
        $subscription->cancel($this->at('2026-04-05'));

        $this->expectException(SubscriptionRefused::class);

        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-04-11'));
    }

    /**
     * Paid, a change within the period keeps the period; to another period, it starts one at the change.
     */
    public function testAPaidChangeTakesEffect(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));
        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-04-11'));
        $subscription->awaitChangePayment('C-1', $this->cad(3_000), $this->cad(667));
        $subscription->paid('C-1', BillingPeriod::of(BillingUnit::Month), $this->at('2026-04-12'));

        self::assertSame('premium', $subscription->offerCode());
        self::assertNull($subscription->pendingChange());
        self::assertNull($subscription->awaitedOrder());
        self::assertSame(1, $subscription->periodsPaid());
        self::assertSame('2026-04-01', $subscription->periodStart()?->format('Y-m-d'));
        self::assertSame('2026-05-01', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(3_000, $subscription->periodAmount()->minorUnits);
        self::assertSame(0, $subscription->carriedCredit()->minorUnits);

        $annual = Offer::create('annual', 'plans', 3, 'Annual', $this->cad(30_000), BillingPeriod::of(BillingUnit::Year), 0, 'digital');
        $subscription->startImmediateChange($annual, $this->at('2026-04-21'));
        $subscription->awaitChangePayment('C-2', $this->cad(30_000), $this->cad(1_000));
        $subscription->paid('C-2', $annual->period, $this->at('2026-04-22'));

        self::assertSame('annual', $subscription->offerCode());
        self::assertSame(2, $subscription->periodsPaid());
        self::assertSame('2026-04-21', $subscription->periodStart()?->format('Y-m-d'));
        self::assertSame('2027-04-21', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertTrue($subscription->isDue($this->at('2027-04-21')));
    }

    /**
     * A downgrade carries its credit over; the next change and the next renewal take it off.
     */
    public function testACarriedCreditIsTakenOffTheNextOrders(): void
    {
        $subscription = $this->active($this->monthly('premium', 2, 3_000));
        $subscription->startImmediateChange($this->monthly('basic', 1, 1_000), $this->at('2026-04-11'));
        $subscription->awaitChangePayment('C-1', $this->cad(1_000), $this->cad(667));
        $subscription->paid('C-1', BillingPeriod::of(BillingUnit::Month), $this->at('2026-04-11'));
        self::assertSame(1_333, $subscription->carriedCredit()->minorUnits);

        $subscription->startImmediateChange($this->monthly('plus', 2, 2_000), $this->at('2026-04-21'));
        self::assertSame(333, $subscription->pendingChange()?->credit->minorUnits);
        $subscription->awaitChangePayment('C-2', $this->cad(2_000), $this->cad(1_000));
        $subscription->paid('C-2', BillingPeriod::of(BillingUnit::Month), $this->at('2026-04-21'));
        self::assertSame(1_333 + 333 - 1_000, $subscription->carriedCredit()->minorUnits);

        $subscription->awaitPayment('R-1', $this->at('2026-05-01'), $this->cad(2_000), $this->cad(500));
        $subscription->paid('R-1', BillingPeriod::of(BillingUnit::Month), $this->at('2026-05-01'));
        self::assertSame(166, $subscription->carriedCredit()->minorUnits);

        $subscription->startImmediateChange($this->monthly('premium', 3, 3_000), $this->at('2026-05-11'));
        $subscription->awaitChangePayment('C-3', $this->cad(3_000), $this->cad(100_000));
        $subscription->paid('C-3', BillingPeriod::of(BillingUnit::Month), $this->at('2026-05-11'));
        self::assertSame(0, $subscription->carriedCredit()->minorUnits, "the credit taken off is at most the change's and the carried");
    }

    /**
     * Without amounts, an order is worth nothing and takes nothing off.
     */
    public function testAnOrderWithoutAmountsIsWorthNothing(): void
    {
        $subscription = Subscription::start($this->monthly('basic', 1, 1_000), 'account-1', $this->at('2026-04-01'));
        $subscription->awaitPayment('F-1', $this->at('2026-04-01'));
        $subscription->paid('F-1', BillingPeriod::of(BillingUnit::Month), $this->at('2026-04-01'));

        self::assertSame(0, $subscription->periodAmount()->minorUnits);
        self::assertSame(0, $subscription->carriedCredit()->minorUnits);
    }

    public function testATrialSwitchesTier(): void
    {
        $subscription = Subscription::start(Offers::pro(), 'account-1', $this->at('2026-04-01'));
        $subscription->changeTo(Offers::free());

        $subscription->switchDuringTrial(Offers::team());

        self::assertSame('team', $subscription->offerCode());
        self::assertSame('team', $subscription->renewalOfferCode());
        self::assertSame(SubscriptionState::Trialing, $subscription->state());
    }

    public function testAFailedChangeIsGivenUp(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));
        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-04-11'));
        $subscription->awaitChangePayment('C-1', $this->cad(3_000), $this->cad(667));

        $subscription->paymentFailed('C-1', 1, $this->at('2026-04-11'));

        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertNull($subscription->pendingChange());
        self::assertNull($subscription->awaitedOrder());
        self::assertSame(0, $subscription->failedPayments());
        self::assertSame('basic', $subscription->renewalOfferCode());
    }

    public function testAChangeNotAwaitedIsAbandoned(): void
    {
        $subscription = $this->active($this->monthly('basic', 1, 1_000));
        $subscription->startImmediateChange($this->monthly('premium', 2, 3_000), $this->at('2026-04-11'));

        $subscription->abandonChange();

        self::assertNull($subscription->pendingChange());
        self::assertSame('basic', $subscription->renewalOfferCode());
    }

    public function testAGiftOfOnePeriod(): void
    {
        $subscription = Subscription::fromGift(Offers::pro(), StableIdentifier::of('friend-1', 'beneficiary'), null, 1, $this->cad(999), $this->at('2026-01-31'));

        self::assertSame(1, $subscription->periodsPaid());
        self::assertSame('2026-02-28', $subscription->periodEnd()?->format('Y-m-d'));
    }

    private function active(Offer $offer): Subscription
    {
        $subscription = Subscription::start($offer, 'account-1', $this->at('2026-04-01'));
        $subscription->awaitPayment('F-1', $this->at('2026-04-01'), $offer->price);
        $subscription->paid('F-1', $offer->period, $this->at('2026-04-01'));

        return $subscription;
    }

    private function monthly(string $code, int $rank, int $price): Offer
    {
        return Offer::create($code, 'plans', $rank, ucfirst($code), $this->cad($price), BillingPeriod::of(BillingUnit::Month), 0, 'digital');
    }

    private function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 00:00:00 UTC');
    }
}
