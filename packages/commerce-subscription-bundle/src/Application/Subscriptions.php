<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Throwable;

/**
 * Subscriptions from start to end, each period paid by an order of the core.
 *
 * The application keeps the loop: `sweep()` once in a while, an order for each subscription it returns, a payment of
 * that order through the core, then `paid()` or `paymentFailed()` when the payment is confirmed or refused. Nothing
 * is committed here.
 *
 * Each change of a subscription made here is told to `FeatureAccess`, when given, so that the features it grants are
 * read again.
 *
 * A change of tier takes effect at the next renewal with `changeTo()`, or at once with `changeNow()`, prorated. A
 * change to a lower tier waits for the next renewal unless `$immediateDowngrades` is set.
 */
final readonly class Subscriptions
{
    /** One period per order: the line's quantity, and the quantity it is priced for. */
    private const int ONE_PERIOD = 1;

    /**
     * @param int  $abandonPendingAfterDays how long a subscription whose first period was never paid stays pending
     *                                      before the sweep ends it
     * @param bool $immediateDowngrades     whether `changeNow()` changes to a lower tier at once too, rather than at
     *                                      the next renewal
     */
    public function __construct(
        private OfferRepository $offers,
        private SubscriptionRepository $subscriptions,
        private OrderModifier $orders,
        private int $paymentAttempts,
        private int $abandonPendingAfterDays = 7,
        private ?FeatureAccess $access = null,
        private bool $immediateDowngrades = false,
    ) {
    }

    public function subscribe(string $subscriber, string $offerCode, DateTimeImmutable $at): Subscription
    {
        $subscription = Subscription::start($this->offer($offerCode), $subscriber, $at);
        $this->subscriptions->add($subscription);
        $this->changed($subscription);

        return $subscription;
    }

    /**
     * Places the order of the subscription's next period, at the renewal tier's current price, and records it as
     * awaited. The application stores the order and starts its payment. The order's line is `subscription:<id>`,
     * which sells the renewal tier even once withdrawn from sale.
     *
     * @param string|null $taxZone the subscriber's tax zone, as the core's tax rates name it
     */
    public function order(Subscription $subscription, string $orderNumber, ?string $taxZone, DateTimeImmutable $at): Order
    {
        if (!$subscription->isDue($at)) {
            throw SubscriptionRefused::notDue($subscription->id);
        }

        $order = $this->place($subscription, $orderNumber, $taxZone, $at);
        $reference = $this->reference($subscription);
        $subscription->awaitPayment($orderNumber, $at, OrderedLine::base($order, $reference), OrderedLine::credit($order, $reference));

        return $order;
    }

    /**
     * Changes tier now rather than at the next renewal, and returns the order to pay, or `null` when nothing is
     * ordered now:
     *
     * - **an active subscription** gets an order of the new tier, prorated: the unused part of the period is credited,
     *   and, within the same period, the new tier is charged for the time left. Its payment, confirmed with `paid()`,
     *   applies the change; its failure, reported with `paymentFailed()`, gives the change up. An order whose total is
     *   zero — a credit that covers it — is confirmed with `paid()` at once;
     * - **a trial** switches tier without an order, and keeps its end;
     * - **a period already over**, the day of the renewal, renews at the new tier, at its whole price;
     * - **a lower tier**, unless `$immediateDowngrades` is set, and the current tier, which takes a change back, wait
     *   for the next renewal, as `changeTo()` does.
     *
     * A pending subscription not ordered yet gets its first period ordered at the new tier; one past due, cancelled or
     * awaiting an order is refused.
     *
     * @param string|null $taxZone the subscriber's tax zone, as the core's tax rates name it
     */
    public function changeNow(Subscription $subscription, string $offerCode, string $orderNumber, ?string $taxZone, DateTimeImmutable $at): ?Order
    {
        $offer = $this->offer($offerCode);
        $current = $this->offer($subscription->offerCode());

        if ($offer->code === $current->code || ($offer->rank < $current->rank && !$this->immediateDowngrades)) {
            $subscription->changeTo($offer);

            return null;
        }

        if (SubscriptionState::Trialing === $subscription->state()) {
            $subscription->switchDuringTrial($offer);
            $this->changed($subscription);

            return null;
        }

        if ($subscription->isDue($at)) {
            $subscription->changeTo($offer);

            return $this->order($subscription, $orderNumber, $taxZone, $at);
        }

        $subscription->startImmediateChange($offer, $at);

        try {
            $order = $this->place($subscription, $orderNumber, $taxZone, $at);
        } catch (Throwable $throwable) {
            $subscription->abandonChange();

            throw $throwable;
        }

        $reference = $this->reference($subscription);
        $subscription->awaitChangePayment($orderNumber, OrderedLine::base($order, $reference), OrderedLine::credit($order, $reference));

        return $order;
    }

    public function paid(Subscription $subscription, string $orderNumber, DateTimeImmutable $at): void
    {
        $subscription->paid($orderNumber, $this->offer($subscription->renewalOfferCode())->period, $at);
        $this->changed($subscription);
    }

    public function paymentFailed(Subscription $subscription, string $orderNumber, DateTimeImmutable $at): void
    {
        $subscription->paymentFailed($orderNumber, $this->paymentAttempts, $at);
        $this->changed($subscription);
    }

    public function cancel(Subscription $subscription, DateTimeImmutable $at): void
    {
        $subscription->cancel($at);
        $this->changed($subscription);
    }

    public function resume(Subscription $subscription): void
    {
        $subscription->resume();
    }

    public function changeTo(Subscription $subscription, string $offerCode): void
    {
        $subscription->changeTo($this->offer($offerCode));
    }

    /**
     * Ends the subscriptions pending for longer than the configured days, those cancelled at the end of a period now
     * over, renews the free ones, and returns those whose next period must be ordered.
     *
     * One subscription the sweep cannot handle — its offer gone, a state it refuses — never stops the others: it is
     * handed to `$refused`, when given, and skipped. `$limit` caps each of the three reads, for a sweep run in
     * batches; what is left is read by the next run.
     *
     * @param (callable(Subscription, SubscriptionRefused): void)|null $refused
     *
     * @return list<Subscription>
     */
    public function sweep(DateTimeImmutable $at, ?callable $refused = null, ?int $limit = null): array
    {
        $due = [];

        foreach ($this->subscriptions->pendingCreatedBefore($at->modify(\sprintf('-%d days', $this->abandonPendingAfterDays)), $limit) as $subscription) {
            $this->attempt($subscription, $refused, static fn () => $subscription->abandon($at));
        }

        foreach ($this->subscriptions->periodOverBy($at, $limit) as $subscription) {
            $this->attempt($subscription, $refused, function () use ($subscription, $at, &$due): void {
                if ($subscription->expire($at) || !$subscription->isDue($at)) {
                    return;
                }

                $offer = $this->offer($subscription->renewalOfferCode());

                if ($offer->isFree() && $subscription->grantsAccess()) {
                    $subscription->renewFree($offer->period, $at);

                    return;
                }

                $due[] = $subscription;
            });
        }

        foreach ($this->subscriptions->pendingWithoutOrder($limit) as $subscription) {
            if ($subscription->isDue($at)) {
                $due[] = $subscription;
            }
        }

        return $due;
    }

    /**
     * @param (callable(Subscription, SubscriptionRefused): void)|null $refused
     * @param callable(): mixed                                        $step
     */
    private function attempt(Subscription $subscription, ?callable $refused, callable $step): void
    {
        try {
            $step();
            $this->changed($subscription);
        } catch (SubscriptionRefused $subscriptionRefused) {
            if (null !== $refused) {
                $refused($subscription, $subscriptionRefused);
            }
        }
    }

    /**
     * The order of one period of the renewal tier, the line `subscription:<id>`, prorated and taxed by the core.
     */
    private function place(Subscription $subscription, string $orderNumber, ?string $taxZone, DateTimeImmutable $at): Order
    {
        $order = Order::open($orderNumber, $this->offer($subscription->renewalOfferCode())->price->currency);
        $this->orders->add($order, $this->reference($subscription), self::ONE_PERIOD, PricingContext::of(self::ONE_PERIOD, $at));
        $this->orders->changeTaxZone($order, $taxZone);
        $this->orders->place($order, $at);

        return $order;
    }

    private function reference(Subscription $subscription): SellableReference
    {
        return SellableReference::of(OfferSellables::SUBSCRIPTION_TYPE, $subscription->id);
    }

    private function changed(Subscription $subscription): void
    {
        $this->access?->forget($subscription->subscriber);
    }

    private function offer(string $code): Offer
    {
        return $this->offers->find($code) ?? throw SubscriptionRefused::unknownOffer($code);
    }
}
