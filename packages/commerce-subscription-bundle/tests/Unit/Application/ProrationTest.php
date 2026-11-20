<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\ConfiguredTaxRates;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Application\Port\OrderProcessor;
use Phil\CommerceBundle\Application\TaxOrderProcessor;
use Phil\CommerceBundle\Domain\Adjustment\AdjustmentType;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\Adjustment;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSubscriptionBundle\Application\FeatureAccess;
use Phil\CommerceSubscriptionBundle\Application\OfferSellables;
use Phil\CommerceSubscriptionBundle\Application\OrderedLine;
use Phil\CommerceSubscriptionBundle\Application\ProrationOrderProcessor;
use Phil\CommerceSubscriptionBundle\Application\Subscriptions;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemoryOffers;
use Phil\CommerceSubscriptionBundle\Tests\Unit\Support\InMemorySubscriptions;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Changes of tier at once, each period of the tier `basic` (10.00 a month) changed on 2026-04-11 — 20 days left of
 * the 30 of April — to `premium` (30.00 a month) or `annual` (300.00 a year), with GST and QST in the zone `QC`.
 */
#[CoversClass(Subscriptions::class)]
#[CoversClass(ProrationOrderProcessor::class)]
#[CoversClass(OrderedLine::class)]
final class ProrationTest extends TestCase
{
    private InMemoryOffers $offers;

    private InMemorySubscriptions $repository;

    protected function setUp(): void
    {
        $this->offers = new InMemoryOffers(
            $this->offer('basic', 1, 1_000, BillingUnit::Month),
            $this->offer('premium', 2, 3_000, BillingUnit::Month),
            $this->offer('annual', 3, 30_000, BillingUnit::Year),
            $this->offer('trial', 1, 1_000, BillingUnit::Month, 14),
            $this->offer('free', 0, 0, BillingUnit::Month),
            Offer::create('yen-basic', 'yen', 1, 'Basic', Money::of(1_000, Currency::of('JPY')), BillingPeriod::of(BillingUnit::Month), 0, 'digital'),
            Offer::create('yen-premium', 'yen', 2, 'Premium', Money::of(3_000, Currency::of('JPY')), BillingPeriod::of(BillingUnit::Month), 0, 'digital'),
        );
        $this->repository = new InMemorySubscriptions();
    }

    /**
     * 20 days of 30 left: the old tier's unused 6.67 is credited, the new tier charged 20.00 of its 30.00, and the
     * 13.33 left are taxed by the core. Paid, the change takes effect and the period keeps its end.
     */
    public function testAnUpgradeIsChargedForTheTimeLeftLessTheUnusedPeriod(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');

        $order = $subscriptions->changeNow($subscription, 'premium', 'C-1', 'QC', $this->at('2026-04-11'));
        self::assertNotNull($order);

        self::assertSame('Premium', $order->lines()[0]->description);
        self::assertSame([
            ['Time already used', -1_000, OrderedLine::UNUSED_TIME],
            ['Credit for the unused period', -667, OrderedLine::CHANGE_CREDIT],
        ], $this->prorations($order));
        self::assertSame(1_333, $order->itemsTotal()->add($order->adjustmentsTotal(AdjustmentType::Proration))->minorUnits);
        self::assertSame(1_333 + 67 + 133, $order->total()->minorUnits);
        self::assertSame('C-1', $subscription->awaitedOrder());
        self::assertSame('premium', $subscription->pendingChange()?->offerCode);
        self::assertSame(667, $subscription->pendingChange()->credit->minorUnits);
        self::assertSame('basic', $subscription->offerCode());

        $subscriptions->paid($subscription, 'C-1', $this->at('2026-04-11'));

        self::assertSame('premium', $subscription->offerCode());
        self::assertNull($subscription->pendingChange());
        self::assertNull($subscription->awaitedOrder());
        self::assertSame('2026-05-01', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(1, $subscription->periodsPaid());
        self::assertSame(3_000, $subscription->periodAmount()->minorUnits);
        self::assertSame(0, $subscription->carriedCredit()->minorUnits);
        self::assertSame(SubscriptionState::Active, $subscription->state());
    }

    /**
     * A second change in the same period credits the time left at the price of the tier changed to first.
     */
    public function testASecondChangeCreditsTheTierOfTheFirst(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');
        $subscriptions->changeNow($subscription, 'premium', 'C-1', null, $this->at('2026-04-11'));
        $subscriptions->paid($subscription, 'C-1', $this->at('2026-04-11'));

        $order = $subscriptions->changeNow($subscription, 'annual', 'C-2', null, $this->at('2026-04-21'));
        self::assertNotNull($order);

        self::assertSame(1_000, $subscription->pendingChange()?->credit->minorUnits);
        self::assertSame(29_000, $order->total()->minorUnits);
    }

    /**
     * To a tier of another period, the new tier is charged a whole period, which starts at the change.
     */
    public function testAChangeOfPeriodStartsAPeriodAtTheChange(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');

        $order = $subscriptions->changeNow($subscription, 'annual', 'C-1', null, $this->at('2026-04-11'));
        self::assertNotNull($order);

        self::assertSame([['Credit for the unused period', -667, OrderedLine::CHANGE_CREDIT]], $this->prorations($order));
        self::assertSame(29_333, $order->total()->minorUnits);
        self::assertTrue($subscription->pendingChange()?->startsAPeriod());

        $subscriptions->paid($subscription, 'C-1', $this->at('2026-04-12'));

        self::assertSame('annual', $subscription->offerCode());
        self::assertSame('2026-04-11', $subscription->periodStart()?->format('Y-m-d'));
        self::assertSame('2027-04-11', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(2, $subscription->periodsPaid());
        self::assertSame(30_000, $subscription->periodAmount()->minorUnits);
    }

    /**
     * A change asked the day of the renewal, the period over: the renewal is ordered at the new tier, whole.
     */
    public function testAChangeTheDayOfTheRenewalRenewsAtTheNewTier(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');

        $order = $subscriptions->changeNow($subscription, 'premium', 'R-1', 'QC', $this->at('2026-05-01'));
        self::assertNotNull($order);

        self::assertSame([], $this->prorations($order));
        self::assertSame(3_000 + 150 + 299, $order->total()->minorUnits);
        self::assertNull($subscription->pendingChange());
        self::assertSame('premium', $subscription->renewalOfferCode());

        $subscriptions->paid($subscription, 'R-1', $this->at('2026-05-01'));

        self::assertSame('premium', $subscription->offerCode());
        self::assertSame('2026-06-01', $subscription->periodEnd()?->format('Y-m-d'));
    }

    /**
     * A trial switches tier at once, without an order, and keeps its end; its features are the new tier's.
     */
    public function testATrialSwitchesTierWithoutAnOrder(): void
    {
        $access = new FeatureAccess($this->repository, $this->offers);
        $subscriptions = $this->subscriptions(access: $access);
        $subscription = $subscriptions->subscribe('account-1', 'trial', $this->at('2026-04-01'));
        self::assertSame(['trial'], $access->features('account-1'));

        self::assertNull($subscriptions->changeNow($subscription, 'premium', 'C-1', null, $this->at('2026-04-05')));

        self::assertSame(SubscriptionState::Trialing, $subscription->state());
        self::assertSame('premium', $subscription->offerCode());
        self::assertSame('2026-04-15', $subscription->periodEnd()?->format('Y-m-d'));
        self::assertSame(['premium'], $access->features('account-1'));
    }

    /**
     * Past due, the renewal's payment retried: the subscriber settles it before changing tier.
     */
    public function testAChangeWhilePastDueIsRefused(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');
        $subscriptions->order($subscription, 'R-1', null, $this->at('2026-05-01'));
        $subscriptions->paymentFailed($subscription, 'R-1', $this->at('2026-05-01'));
        self::assertSame(SubscriptionState::PastDue, $subscription->state());

        $this->expectException(SubscriptionRefused::class);
        $this->expectExceptionMessageIsOrContains('change tier at once unless active, not cancelled, awaiting no order, and to another tier');

        $subscriptions->changeNow($subscription, 'premium', 'C-1', null, $this->at('2026-05-02'));
    }

    public function testACancelledSubscriptionIsRefused(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');
        $subscriptions->cancel($subscription, $this->at('2026-04-05'));

        $this->expectException(SubscriptionRefused::class);

        $subscriptions->changeNow($subscription, 'premium', 'C-1', null, $this->at('2026-04-11'));
    }

    /**
     * In yen, without decimals: 23 days of 30 left credit 766.67, rounded to 767, and charge 2,300.
     */
    public function testACurrencyWithoutDecimalsIsRoundedToTheYen(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'yen-basic');

        $order = $subscriptions->changeNow($subscription, 'yen-premium', 'C-1', null, $this->at('2026-04-08'));
        self::assertNotNull($order);

        self::assertSame('JPY', $order->currency->code);
        self::assertSame([
            ['Time already used', -700, OrderedLine::UNUSED_TIME],
            ['Credit for the unused period', -767, OrderedLine::CHANGE_CREDIT],
        ], $this->prorations($order));
        self::assertSame(1_533, $order->total()->minorUnits);
    }

    /**
     * A lower tier waits for the next renewal unless immediate downgrades are set.
     */
    public function testADowngradeWaitsForTheRenewalByDefault(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'premium');

        self::assertNull($subscriptions->changeNow($subscription, 'basic', 'C-1', null, $this->at('2026-04-11')));

        self::assertSame('premium', $subscription->offerCode());
        self::assertSame('basic', $subscription->renewalOfferCode());
        self::assertNull($subscription->pendingChange());
        self::assertNull($subscription->awaitedOrder());
    }

    /**
     * Downgraded at once, the credit is more than the new tier costs: the order is zero, and what it does not use is
     * carried over to the renewals until spent.
     */
    public function testAnImmediateDowngradeCarriesItsCreditOver(): void
    {
        $subscriptions = $this->subscriptions(immediateDowngrades: true);
        $subscription = $this->active($subscriptions, 'premium');

        $order = $subscriptions->changeNow($subscription, 'basic', 'C-1', 'QC', $this->at('2026-04-11'));
        self::assertNotNull($order);

        self::assertSame([
            ['Time already used', -333, OrderedLine::UNUSED_TIME],
            ['Credit for the unused period', -667, OrderedLine::CHANGE_CREDIT],
        ], $this->prorations($order));
        self::assertTrue($order->total()->isZero());

        $subscriptions->paid($subscription, 'C-1', $this->at('2026-04-11'));
        self::assertSame('basic', $subscription->offerCode());
        self::assertSame(1_333, $subscription->carriedCredit()->minorUnits);

        $renewal = $subscriptions->order($subscription, 'R-1', null, $this->at('2026-05-01'));
        self::assertSame([['Credit carried over', -1_000, OrderedLine::CARRIED_CREDIT]], $this->prorations($renewal));
        self::assertTrue($renewal->total()->isZero());
        $subscriptions->paid($subscription, 'R-1', $this->at('2026-05-01'));
        self::assertSame(333, $subscription->carriedCredit()->minorUnits);
        self::assertSame(1_000, $subscription->periodAmount()->minorUnits);

        $next = $subscriptions->order($subscription, 'R-2', null, $this->at('2026-06-01'));
        self::assertSame(667, $next->total()->minorUnits);
        $subscriptions->paid($subscription, 'R-2', $this->at('2026-06-01'));
        self::assertSame(0, $subscription->carriedCredit()->minorUnits);
    }

    /**
     * A credit carried over is not lost when its renewal fails: the next try takes it off again.
     */
    public function testACarriedCreditSurvivesAFailedRenewal(): void
    {
        $subscriptions = $this->subscriptions(immediateDowngrades: true);
        $subscription = $this->active($subscriptions, 'premium');
        $subscriptions->changeNow($subscription, 'free', 'C-1', null, $this->at('2026-04-11'));
        $subscriptions->paid($subscription, 'C-1', $this->at('2026-04-11'));
        self::assertSame(2_000, $subscription->carriedCredit()->minorUnits);
        $subscriptions->changeNow($subscription, 'basic', 'C-2', null, $this->at('2026-04-21'));

        self::assertSame(2_000, $subscription->carriedCredit()->minorUnits);
        self::assertSame(0, $subscription->pendingChange()?->credit->minorUnits);

        $subscriptions->paymentFailed($subscription, 'C-2', $this->at('2026-04-21'));

        self::assertSame(2_000, $subscription->carriedCredit()->minorUnits);
        self::assertSame('free', $subscription->offerCode());
    }

    /**
     * The failed payment of a change gives it up: the subscription stays as it was, never past due.
     */
    public function testAFailedChangeLeavesTheSubscriptionAsItWas(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');
        $subscriptions->changeNow($subscription, 'premium', 'C-1', null, $this->at('2026-04-11'));

        $subscriptions->paymentFailed($subscription, 'C-1', $this->at('2026-04-11'));

        self::assertSame(SubscriptionState::Active, $subscription->state());
        self::assertSame('basic', $subscription->offerCode());
        self::assertSame('basic', $subscription->renewalOfferCode());
        self::assertNull($subscription->pendingChange());
        self::assertNull($subscription->awaitedOrder());
        self::assertSame(0, $subscription->failedPayments());
        self::assertSame(1_000, $subscription->periodAmount()->minorUnits);
        self::assertSame(1_000, $subscriptions->order($subscription, 'R-1', null, $this->at('2026-05-01'))->total()->minorUnits);
    }

    /**
     * An order that cannot be placed gives the change up before anything is awaited.
     */
    public function testAnOrderThatCannotBePlacedGivesTheChangeUp(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');

        try {
            $subscriptions->changeNow($subscription, 'premium', 'not a number', null, $this->at('2026-04-11'));
            self::fail('The order was placed.');
        } catch (InvalidValue) {
        }

        self::assertNull($subscription->pendingChange());
        self::assertNull($subscription->awaitedOrder());
        self::assertSame('basic', $subscription->renewalOfferCode());
    }

    /**
     * Asking for the current tier takes a change waiting for the renewal back.
     */
    public function testTheCurrentTierTakesAChangeBack(): void
    {
        $subscriptions = $this->subscriptions();
        $subscription = $this->active($subscriptions, 'basic');
        $subscriptions->changeTo($subscription, 'premium');

        self::assertNull($subscriptions->changeNow($subscription, 'basic', 'C-1', null, $this->at('2026-04-11')));

        self::assertSame('basic', $subscription->renewalOfferCode());
    }

    /**
     * A promotion on the line lowers what the period is worth, and the credit with it.
     */
    public function testTheProrationReadsTheLineAfterItsPromotions(): void
    {
        $promotion = new readonly class implements OrderProcessor {
            #[Override]
            public function process(Order $order): void
            {
                $adjustments = [];

                foreach ($order->lines() as $line) {
                    $adjustments[] = Adjustment::of(AdjustmentType::Promotion, 'Half', $line->subtotal()->allocate(1, 1)[0]->negate(), $line->reference);
                }

                $order->replaceAdjustments(AdjustmentType::Promotion, $adjustments);
            }
        };
        $subscriptions = $this->subscriptions(before: [$promotion]);
        $subscription = $this->active($subscriptions, 'basic');
        self::assertSame(500, $subscription->periodAmount()->minorUnits);

        $order = $subscriptions->changeNow($subscription, 'premium', 'C-1', null, $this->at('2026-04-11'));
        self::assertNotNull($order);

        self::assertSame([
            ['Time already used', -500, OrderedLine::UNUSED_TIME],
            ['Credit for the unused period', -333, OrderedLine::CHANGE_CREDIT],
        ], $this->prorations($order));
        self::assertSame(667, $order->total()->minorUnits);
    }

    /**
     * Lines the module does not sell, subscriptions gone, and orders in another currency are left alone.
     */
    public function testOtherLinesAreLeftAlone(): void
    {
        $processor = new ProrationOrderProcessor($this->repository);
        $subscription = $this->active($this->subscriptions(), 'basic');
        $subscription->startImmediateChange($this->offers->find('premium') ?? self::fail(), $this->at('2026-04-11'));

        foreach ([['offer', 'premium', 'CAD'], ['subscription', 'gone', 'CAD'], ['subscription', $subscription->id, 'JPY']] as [$type, $id, $currency]) {
            $order = Order::open('O-1', Currency::of($currency));
            $order->add(Sellable::of(SellableReference::of($type, $id), 'Premium', Money::of(3_000, Currency::of($currency)), 'digital'), 1, Money::of(3_000, Currency::of($currency)));

            $processor->process($order);

            self::assertSame([], $this->prorations($order), $type.':'.$id.' in '.$currency);
        }
    }

    /**
     * A line the processor skips does not stop it: the subscription line after it is prorated.
     */
    public function testALineSkippedDoesNotStopTheOthers(): void
    {
        $processor = new ProrationOrderProcessor($this->repository);
        $subscription = $this->active($this->subscriptions(), 'basic');
        $subscription->startImmediateChange($this->offers->find('premium') ?? self::fail(), $this->at('2026-04-11'));

        $cad = Currency::of('CAD');

        foreach ([[SellableReference::of('offer', 'premium'), 3_000], [SellableReference::of('subscription', 'gone'), 3_000], [SellableReference::of('subscription', $this->subscriptions()->subscribe('x', 'free', $this->at('2026-04-01'))->id), 0]] as [$first, $price]) {
            $order = Order::open('O-1', $cad);
            $order->add(Sellable::of($first, 'First', Money::of($price, $cad), 'digital'), 1, Money::of($price, $cad));
            $order->add(Sellable::of(SellableReference::of('subscription', $subscription->id), 'Premium', Money::of(3_000, $cad), 'digital'), 1, Money::of(3_000, $cad));

            $processor->process($order);

            self::assertCount(2, $this->prorations($order), $first->value());
        }
    }

    /**
     * Without being told, a lower tier waits for the renewal; a tier of the same rank changes at once.
     */
    public function testDowngradesWaitByDefaultAndTheSameRankChangesAtOnce(): void
    {
        $this->offers->add($this->offer('studio', 1, 1_500, BillingUnit::Month));
        $subscriptions = new Subscriptions($this->offers, $this->repository, new OrderModifier([new OfferSellables($this->offers, $this->repository)], [], [new ProrationOrderProcessor($this->repository)]), 2);
        $premium = $this->active($subscriptions, 'premium');

        self::assertNull($subscriptions->changeNow($premium, 'basic', 'C-1', null, $this->at('2026-04-11')));

        $basic = $this->active($subscriptions, 'basic');
        self::assertSame(1_000 - 667, $subscriptions->changeNow($basic, 'studio', 'C-2', null, $this->at('2026-04-11'))?->total()->minorUnits);
    }

    /**
     * @param list<OrderProcessor> $before the processors that run before the proration
     */
    private function subscriptions(bool $immediateDowngrades = false, ?FeatureAccess $access = null, array $before = []): Subscriptions
    {
        $processors = [...$before, new ProrationOrderProcessor($this->repository), new TaxOrderProcessor(new ConfiguredTaxRates(['digital' => ['QC' => ['GST' => '5', 'QST' => '9.975']]]))];

        return new Subscriptions($this->offers, $this->repository, new OrderModifier([new OfferSellables($this->offers, $this->repository)], [], $processors), 2, 7, $access, $immediateDowngrades);
    }

    /**
     * A subscription to the offer, its first period paid on 2026-04-01.
     */
    private function active(Subscriptions $subscriptions, string $offer): Subscription
    {
        $subscription = $subscriptions->subscribe('account-1', $offer, $this->at('2026-04-01'));
        $subscriptions->order($subscription, 'F-'.$offer, null, $this->at('2026-04-01'));
        $subscriptions->paid($subscription, 'F-'.$offer, $this->at('2026-04-01'));

        return $subscription;
    }

    private function offer(string $code, int $rank, int $price, BillingUnit $unit, int $trialDays = 0): Offer
    {
        return Offer::create($code, 'plans', $rank, ucfirst($code), Money::of($price, Currency::of('CAD')), BillingPeriod::of($unit), $trialDays, 'digital', [$code]);
    }

    /**
     * @return list<array{string, int, ?string}>
     */
    private function prorations(?Order $order): array
    {
        return array_map(static fn (Adjustment $adjustment): array => [$adjustment->label, $adjustment->amount->minorUnits, $adjustment->origin], $order?->adjustments(AdjustmentType::Proration) ?? []);
    }

    private function at(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date.' 00:00:00 UTC');
    }
}
