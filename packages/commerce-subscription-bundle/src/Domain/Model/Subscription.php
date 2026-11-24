<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Offer\BillingUnit;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\BillingPeriod;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\ImmediateChange;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * A subscriber's subscription to one offer: the periods it paid, counted from its anchor, and the order of the period
 * it awaits payment for.
 *
 * - **Each period is paid by an order of the core.** The subscription records the order it awaits; the payment of
 *   that order activates or renews it, its failure makes it past due, and the last failure ends it.
 * - **Cancelling ends it at the end of the paid period**, never before: the subscriber keeps what they paid for.
 * - **A change of tier takes effect at the next renewal**, on the order of the next period — or **at once**, on an
 *   order of its own whose payment applies it: the unused part of the period is credited, and what the order does not
 *   use of that credit is carried over to the next orders.
 * - **A free offer renews without an order.**
 * - **A gift** starts active for the periods its buyer paid, renewed at the buyer's charge or ended at its term.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_subscription')]
#[ORM\Index(columns: ['subscriber'])]
#[ORM\Index(columns: ['state', 'period_end'])]
final class Subscription
{
    #[ORM\Column(name: 'state', length: 16, enumType: SubscriptionState::class)]
    private SubscriptionState $state = SubscriptionState::Pending;

    #[ORM\Column(name: 'next_offer', length: 64, nullable: true)]
    private ?string $nextOfferCode = null;

    /** The start of the first paid period; every period end is counted from it. */
    #[ORM\Column(name: 'anchor', nullable: true)]
    private ?DateTimeImmutable $anchor = null;

    #[ORM\Column(name: 'periods_paid', type: 'integer')]
    private int $periodsPaid = 0;

    /** The periods paid since the anchor, at the period below; a change of period moves the anchor. */
    #[ORM\Column(name: 'periods_since_anchor', type: 'integer')]
    private int $periodsSinceAnchor = 0;

    #[ORM\Column(name: 'period_unit', length: 8, nullable: true, enumType: BillingUnit::class)]
    private ?BillingUnit $periodUnit = null;

    #[ORM\Column(name: 'period_count', type: 'smallint')]
    private int $periodCount = 0;

    /** The end of the trial, or of the last period paid; `null` while pending. */
    #[ORM\Column(name: 'period_end', nullable: true)]
    private ?DateTimeImmutable $periodEnd = null;

    #[ORM\Column(name: 'cancel_at_period_end')]
    private bool $cancelAtPeriodEnd = false;

    #[ORM\Column(name: 'awaited_order', length: 64, nullable: true)]
    private ?string $awaitedOrder = null;

    #[ORM\Column(name: 'failed_payments', type: 'smallint')]
    private int $failedPayments = 0;

    /** When its renewal first failed, while past due: what the offer's dunning stages count from. */
    #[ORM\Column(name: 'past_due_since', nullable: true)]
    private ?DateTimeImmutable $pastDueSince = null;

    #[ORM\Column(name: 'ended_at', nullable: true)]
    private ?DateTimeImmutable $endedAt = null;

    /** Who pays the renewals when it is not the subscriber: the buyer of a gift. */
    #[ORM\Column(name: 'payer', length: 255, nullable: true)]
    private ?string $payer = null;

    /** The price of a whole period of the current tier, as last ordered: what the unused part is credited from. */
    #[ORM\Column(name: 'period_amount', type: 'bigint')]
    private int $periodAmount = 0;

    /** The credit left over from an immediate change, taken off the next orders. */
    #[ORM\Column(name: 'carried_credit', type: 'bigint')]
    private int $carriedCredit = 0;

    /** The price of a whole period on the awaited order. */
    #[ORM\Column(name: 'awaited_amount', type: 'bigint')]
    private int $awaitedAmount = 0;

    /** The credit the awaited order takes off, carried over or from its immediate change. */
    #[ORM\Column(name: 'awaited_credit', type: 'bigint')]
    private int $awaitedCredit = 0;

    #[ORM\Column(name: 'change_offer', length: 64, nullable: true)]
    private ?string $changeOfferCode = null;

    #[ORM\Column(name: 'change_at', nullable: true)]
    private ?DateTimeImmutable $changeAt = null;

    #[ORM\Column(name: 'change_seconds_left', type: 'integer')]
    private int $changeSecondsLeft = 0;

    #[ORM\Column(name: 'change_period_seconds', type: 'integer', nullable: true)]
    private ?int $changePeriodSeconds = null;

    #[ORM\Column(name: 'change_credit', type: 'bigint')]
    private int $changeCredit = 0;

    /**
     * Where its shipments go, as `DeliveryAddress::toArray()` writes it; `null` when nothing is shipped to the
     * subscriber, or once erased.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(name: 'delivery_address', type: 'json', nullable: true)]
    private ?array $deliveryAddress = null;

    /** When its last shipment left: what the erasure of its delivery address counts from once it ended. */
    #[ORM\Column(name: 'last_shipped_at', nullable: true)]
    private ?DateTimeImmutable $lastShippedAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'subscriber', length: 255)]
        public readonly string $subscriber,
        #[ORM\Column(name: 'family', length: 64)]
        public readonly string $family,
        #[ORM\Column(name: 'offer', length: 64)]
        private string $offerCode,
        #[ORM\Column(name: 'created_at')]
        public readonly DateTimeImmutable $createdAt,
        /** The currency of the family's prices: every amount the subscription keeps is in it. */
        #[ORM\Column(name: 'currency', length: 3, options: ['fixed' => true])]
        private string $currencyCode,
    ) {
    }

    /**
     * A free offer starts active, an offer with a trial starts trialing, any other waits for its first payment.
     *
     * @param string $subscriber the application's id of the subscriber, an account or a tenant
     */
    public static function start(Offer $offer, string $subscriber, DateTimeImmutable $at): self
    {
        if ('' === trim($subscriber) || \strlen($subscriber) > 255) {
            throw InvalidValue::because('A subscriber is named by 1 to 255 characters.');
        }

        if (!$offer->isActive()) {
            throw SubscriptionRefused::withdrawn($offer->code);
        }

        $subscription = new self(Uuid::v7()->toRfc4122(), $subscriber, $offer->family, $offer->code, $at, $offer->price->currency->code);

        if ($offer->isFree()) {
            $subscription->activate($at, $offer->period);
        } elseif ($offer->trialDays > 0) {
            $subscription->state = SubscriptionState::Trialing;
            $subscription->periodEnd = $at->modify(\sprintf('+%d days', $offer->trialDays));
        }

        return $subscription;
    }

    /**
     * A gift's subscription, active at once for the `$periods` its buyer paid, whatever the offer's trial; the offer
     * may have been withdrawn since the gift was bought.
     *
     * @param string|null $payer        who pays the renewals, the buyer; `null` when it ends at its term
     * @param Money       $periodAmount the price of one period, as the buyer paid it
     */
    public static function fromGift(Offer $offer, StableIdentifier $beneficiary, ?string $payer, int $periods, Money $periodAmount, DateTimeImmutable $at): self
    {
        if ($periods < 1 || $periods > 36) {
            throw InvalidValue::because(\sprintf('A gift pays 1 to 36 periods; %d does not.', $periods));
        }

        $subscription = new self(Uuid::v7()->toRfc4122(), $beneficiary->value, $offer->family, $offer->code, $at, $offer->price->currency->code);
        $subscription->payer = $payer;
        $subscription->anchor = $at;
        $subscription->periodsPaid = $periods;
        $subscription->periodsSinceAnchor = $periods;
        $subscription->periodUnit = $offer->period->unit;
        $subscription->periodCount = $offer->period->count;
        $subscription->periodEnd = $offer->period->after($at, $periods);
        $subscription->periodAmount = $subscription->amount($periodAmount);
        $subscription->state = SubscriptionState::Active;
        $subscription->cancelAtPeriodEnd = null === $payer;

        return $subscription;
    }

    public function state(): SubscriptionState
    {
        return $this->state;
    }

    public function offerCode(): string
    {
        return $this->offerCode;
    }

    /**
     * The offer the next period is ordered at: the tier changed to, or the current one.
     */
    public function renewalOfferCode(): string
    {
        return $this->changeOfferCode ?? $this->nextOfferCode ?? $this->offerCode;
    }

    public function currency(): Currency
    {
        return Currency::of($this->currencyCode);
    }

    /**
     * Who pays the renewals when it is not the subscriber — the buyer of a gift —, as the application named them.
     */
    public function payer(): ?string
    {
        return $this->payer;
    }

    /**
     * The price of a whole period of the current tier, as last ordered: zero for a free tier.
     */
    public function periodAmount(): Money
    {
        return Money::of($this->periodAmount, $this->currency());
    }

    /**
     * The credit left over from an immediate change, which the next orders take off.
     */
    public function carriedCredit(): Money
    {
        return Money::of($this->carriedCredit, $this->currency());
    }

    /**
     * The change of tier taking effect at once that waits for its order to be paid, if any.
     */
    public function pendingChange(): ?ImmediateChange
    {
        if (!$this->changeAt instanceof DateTimeImmutable) {
            return null;
        }

        return ImmediateChange::of($this->changeOfferCode ?? $this->offerCode, $this->changeAt, $this->changeSecondsLeft, $this->changePeriodSeconds, Money::of($this->changeCredit, $this->currency()));
    }

    /**
     * The start of the period paid last; `null` until a period is paid.
     */
    public function periodStart(): ?DateTimeImmutable
    {
        if (!$this->anchor instanceof DateTimeImmutable) {
            return null;
        }

        return BillingPeriod::of($this->periodUnit ?? BillingUnit::Day, $this->periodCount)->after($this->anchor, $this->periodsSinceAnchor - 1);
    }

    public function periodEnd(): ?DateTimeImmutable
    {
        return $this->periodEnd;
    }

    public function periodsPaid(): int
    {
        return $this->periodsPaid;
    }

    public function awaitedOrder(): ?string
    {
        return $this->awaitedOrder;
    }

    public function failedPayments(): int
    {
        return $this->failedPayments;
    }

    public function cancelsAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    /**
     * When the renewal first failed, while the subscription is past due; `null` otherwise.
     */
    public function pastDueSince(): ?DateTimeImmutable
    {
        return SubscriptionState::PastDue === $this->state ? $this->pastDueSince : null;
    }

    /**
     * How many whole days the subscription has been past due at that moment; `null` when it is not.
     */
    public function daysPastDue(DateTimeImmutable $at): ?int
    {
        $since = $this->pastDueSince();

        if (!$since instanceof DateTimeImmutable) {
            return null;
        }

        return max(0, intdiv($at->getTimestamp() - $since->getTimestamp(), 86_400));
    }

    public function endedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    /**
     * Where its next shipments go, or `null`. The orders already placed keep the copy they were placed with.
     */
    public function deliveryAddress(): ?DeliveryAddress
    {
        return null === $this->deliveryAddress ? null : DeliveryAddress::fromArray($this->deliveryAddress);
    }

    public function shipTo(DeliveryAddress $address): void
    {
        $this->deliveryAddress = $address->toArray();
    }

    /**
     * Forgets its delivery address, and when its last shipment left; whether it had one.
     */
    public function forgetDeliveryAddress(): bool
    {
        $had = null !== $this->deliveryAddress || $this->lastShippedAt instanceof DateTimeImmutable;
        $this->deliveryAddress = null;
        $this->lastShippedAt = null;

        return $had;
    }

    /**
     * Records a shipment that left at `$at`; an earlier one recorded late keeps the latest.
     */
    public function shipped(DateTimeImmutable $at): void
    {
        if (!$this->lastShippedAt instanceof DateTimeImmutable || $at > $this->lastShippedAt) {
            $this->lastShippedAt = $at;
        }
    }

    public function lastShippedAt(): ?DateTimeImmutable
    {
        return $this->lastShippedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Whether the subscriber has the features of the offer: trialing, active, or past due while retries last.
     */
    public function grantsAccess(): bool
    {
        return \in_array($this->state, [SubscriptionState::Trialing, SubscriptionState::Active, SubscriptionState::PastDue], true);
    }

    /**
     * Whether a period must be ordered now: a pending subscription without an order, or a trial or a period over that
     * is not cancelled and awaits no order yet.
     */
    public function isDue(DateTimeImmutable $at): bool
    {
        if (null !== $this->awaitedOrder || $this->cancelAtPeriodEnd) {
            return false;
        }

        return match ($this->state) {
            SubscriptionState::Pending => true,
            SubscriptionState::Trialing, SubscriptionState::Active => $at >= $this->periodEnd,
            default => false,
        };
    }

    /**
     * Whether the next period may be ordered, whatever the moment: not ended, not cancelled, and no order awaited
     * yet. `isDue()` adds that the period is over.
     */
    public function awaitsAnOrder(): bool
    {
        return null === $this->awaitedOrder && !$this->cancelAtPeriodEnd && SubscriptionState::Ended !== $this->state;
    }

    /**
     * The order of the next period was placed; the subscription awaits its payment.
     *
     * @param Money|null $amount the price of a whole period on the order, which a later immediate change credits from
     * @param Money|null $credit the carried credit the order takes off
     */
    public function awaitPayment(string $orderNumber, DateTimeImmutable $at, ?Money $amount = null, ?Money $credit = null): void
    {
        if (!$this->isDue($at)) {
            throw SubscriptionRefused::notDue($this->id);
        }

        $this->awaitedOrder = $orderNumber;
        $this->awaitedAmount = $amount instanceof Money ? $this->amount($amount) : 0;
        $this->awaitedCredit = $credit instanceof Money ? min($this->amount($credit), $this->carriedCredit) : 0;
    }

    /**
     * Changes tier at once, within the family: the change waits for the payment of its order, which
     * `awaitChangePayment()` records, and the unused part of the current period is credited.
     *
     * Only an active subscription that awaits no order and is not cancelled changes at once. A trial changes with
     * `switchDuringTrial()`, and a period already over renews at the new tier with `changeTo()`.
     */
    public function startImmediateChange(Offer $offer, DateTimeImmutable $at): void
    {
        $this->checkChange($offer);
        // A period was paid: the subscription is active, past due or ended, and the last two await no order.
        $start = $this->periodStart();

        if (!$start instanceof DateTimeImmutable || !$this->awaitsAnOrder() || $offer->code === $this->offerCode) {
            throw SubscriptionRefused::state($this->id, $this->state, 'change tier at once unless active, not cancelled, awaiting no order, and to another tier');
        }

        $end = $this->periodEnd ?? $start;

        if ($at >= $end) {
            throw SubscriptionRefused::periodOver($this->id);
        }

        $length = $end->getTimestamp() - $start->getTimestamp();
        // A period that starts later — paid before the trial it follows ended — is left whole.
        $left = min($end->getTimestamp() - $at->getTimestamp(), $length);
        $sameLength = $offer->period->equals(BillingPeriod::of($this->periodUnit ?? BillingUnit::Day, $this->periodCount));

        $this->changeOfferCode = $offer->code;
        $this->changeAt = $at;
        $this->changeSecondsLeft = $left;
        $this->changePeriodSeconds = $sameLength ? $length : null;
        $this->changeCredit = $this->periodAmount()->allocate($left, $length - $left)[0]->minorUnits;
    }

    /**
     * The order of the immediate change was placed; the subscription awaits its payment, which applies the change.
     *
     * @param Money $amount the price of a whole period of the new tier on the order
     * @param Money $credit the credit the order takes off, from the change and carried over
     */
    public function awaitChangePayment(string $orderNumber, Money $amount, Money $credit): void
    {
        if (null === $this->changeOfferCode || null !== $this->awaitedOrder) {
            throw SubscriptionRefused::state($this->id, $this->state, 'await the payment of a change it has not started');
        }

        $this->awaitedOrder = $orderNumber;
        $this->awaitedAmount = $this->amount($amount);
        $this->awaitedCredit = min($this->amount($credit), $this->changeCredit + $this->carriedCredit);
    }

    /**
     * Gives up an immediate change whose order is not awaited yet: the subscription stays as it was.
     */
    public function abandonChange(): void
    {
        if (null !== $this->awaitedOrder) {
            throw SubscriptionRefused::state($this->id, $this->state, 'give up a change whose order awaits its payment');
        }

        $this->clearChange();
    }

    /**
     * Changes the tier of a trial at once, without an order: the trial keeps its end, and its features are the new
     * tier's.
     */
    public function switchDuringTrial(Offer $offer): void
    {
        $this->checkChange($offer);

        if (SubscriptionState::Trialing !== $this->state || null !== $this->awaitedOrder) {
            throw SubscriptionRefused::state($this->id, $this->state, 'switch tier as a trial');
        }

        $this->offerCode = $offer->code;
        $this->nextOfferCode = null;
    }

    /**
     * The awaited order was paid: the first period starts, or the next one is added; a change of tier takes effect.
     */
    public function paid(string $orderNumber, BillingPeriod $period, DateTimeImmutable $at): void
    {
        $this->checkAwaited($orderNumber);
        $this->offerCode = $this->renewalOfferCode();
        $this->nextOfferCode = null;
        $this->awaitedOrder = null;
        $this->failedPayments = 0;
        $this->periodAmount = $this->awaitedAmount;

        if ($this->changeAt instanceof DateTimeImmutable) {
            $this->applyChange($this->changeAt, $period);

            return;
        }

        $this->carriedCredit -= $this->awaitedCredit;
        $this->activate($at, $period);
    }

    /**
     * A free renewal, without an order: the next period is added. A trial that ends on a free tier — the subscriber
     * changed to it during the trial — becomes active the same way.
     */
    public function renewFree(BillingPeriod $period, DateTimeImmutable $at): void
    {
        if (!$this->isDue($at) || !\in_array($this->state, [SubscriptionState::Active, SubscriptionState::Trialing], true)) {
            throw SubscriptionRefused::notDue($this->id);
        }

        $this->offerCode = $this->renewalOfferCode();
        $this->nextOfferCode = null;
        $this->periodAmount = 0;
        $this->activate($at, $period);
    }

    /**
     * A payment of the awaited order failed. A pending subscription simply waits for another try; a trial or a
     * renewal becomes past due, and ends after `$attempts` failures — at once when the subscriber cancelled while the
     * payment was in flight, since nobody wants the period retried.
     *
     * The failed payment of an immediate change gives the change up: the subscription stays as it was, never past due.
     */
    public function paymentFailed(string $orderNumber, int $attempts, DateTimeImmutable $at): void
    {
        $this->checkAwaited($orderNumber);

        if ($this->changeAt instanceof DateTimeImmutable) {
            $this->awaitedOrder = null;
            $this->clearChange();

            return;
        }

        ++$this->failedPayments;

        if (SubscriptionState::Pending === $this->state) {
            return;
        }

        $this->state = SubscriptionState::PastDue;
        $this->pastDueSince ??= $at;

        if ($this->failedPayments >= $attempts || $this->cancelAtPeriodEnd) {
            $this->end($at);
        }
    }

    /**
     * Ends a pending subscription whose first period was never paid: abandoned at sign-up, or its first payment
     * failed and not tried again.
     */
    public function abandon(DateTimeImmutable $at): void
    {
        if (SubscriptionState::Pending !== $this->state) {
            throw SubscriptionRefused::state($this->id, $this->state, 'be abandoned');
        }

        $this->end($at);
    }

    /**
     * Ends the subscription at the end of the paid period, or now if nothing was paid.
     */
    public function cancel(DateTimeImmutable $at): void
    {
        match ($this->state) {
            SubscriptionState::Ended => throw SubscriptionRefused::state($this->id, $this->state, 'be cancelled'),
            SubscriptionState::Pending, SubscriptionState::PastDue => $this->end($at),
            default => $this->cancelAtPeriodEnd = true,
        };
    }

    /**
     * Takes a cancellation back before the period ends.
     */
    public function resume(): void
    {
        if (!$this->cancelAtPeriodEnd || SubscriptionState::Ended === $this->state) {
            throw SubscriptionRefused::state($this->id, $this->state, 'be resumed');
        }

        $this->cancelAtPeriodEnd = false;
    }

    /**
     * The tier the next period is ordered at, within the family; the current tier, to take a change back.
     */
    public function changeTo(Offer $offer): void
    {
        $this->checkChange($offer);

        if (SubscriptionState::Ended === $this->state || null !== $this->awaitedOrder) {
            throw SubscriptionRefused::state($this->id, $this->state, 'change tier while it awaits a payment or once ended');
        }

        $this->nextOfferCode = $offer->code === $this->offerCode ? null : $offer->code;
    }

    /**
     * Ends a subscription cancelled at the end of its period, once that end has come; `false` otherwise.
     *
     * A subscription that awaits the payment of its next period's order is left to that payment: paid, the period is
     * granted — the subscriber keeps what they paid for — and the subscription ends at its end; failed, it ends then.
     * Ending it now would turn a payment captured later into money for nothing.
     */
    public function expire(DateTimeImmutable $at): bool
    {
        if (!$this->cancelAtPeriodEnd || SubscriptionState::Ended === $this->state || $at < $this->periodEnd || null !== $this->awaitedOrder) {
            return false;
        }

        $this->end($at);

        return true;
    }

    private function activate(DateTimeImmutable $at, BillingPeriod $period): void
    {
        if (!$this->anchor instanceof DateTimeImmutable) {
            // The first period starts now, or when the trial ends if it is paid early.
            $this->anchor = SubscriptionState::Trialing === $this->state ? max($this->periodEnd, $at) : $at;
            $this->periodsSinceAnchor = 0;
        } elseif ($this->periodUnit !== $period->unit || $this->periodCount !== $period->count) {
            // A tier of another period starts where the last period paid ends.
            $this->anchor = $this->periodEnd ?? $this->anchor;
            $this->periodsSinceAnchor = 0;
        }

        ++$this->periodsPaid;
        ++$this->periodsSinceAnchor;
        $this->periodUnit = $period->unit;
        $this->periodCount = $period->count;
        $this->periodEnd = $period->after($this->anchor, $this->periodsSinceAnchor);
        $this->state = SubscriptionState::Active;
        $this->pastDueSince = null;
    }

    /**
     * The paid change takes effect: the new tier at once, the credit it did not use carried over, and a period of its
     * own from the change when the new tier's period is another.
     */
    private function applyChange(DateTimeImmutable $changedAt, BillingPeriod $period): void
    {
        $this->carriedCredit += $this->changeCredit - $this->awaitedCredit;

        if (null === $this->changePeriodSeconds) {
            $this->anchor = $changedAt;
            $this->periodsSinceAnchor = 1;
            ++$this->periodsPaid;
            $this->periodUnit = $period->unit;
            $this->periodCount = $period->count;
            $this->periodEnd = $period->after($changedAt, 1);
        }

        $this->clearChange();
    }

    private function clearChange(): void
    {
        $this->changeOfferCode = null;
        $this->changeAt = null;
        $this->changePeriodSeconds = null;
    }

    private function checkChange(Offer $offer): void
    {
        if ($offer->family !== $this->family) {
            throw SubscriptionRefused::otherFamily($offer->code, $this->family);
        }

        if (!$offer->isActive()) {
            throw SubscriptionRefused::withdrawn($offer->code);
        }

        if (!$offer->price->currency->equals($this->currency())) {
            throw SubscriptionRefused::otherCurrency($offer->code, $this->currencyCode);
        }
    }

    /**
     * The minor units of an amount in the subscription's currency, zero or more.
     */
    private function amount(Money $amount): int
    {
        if (!$amount->currency->equals($this->currency()) || $amount->isNegative()) {
            throw InvalidValue::because(\sprintf('The subscription %s keeps amounts of zero or more in %s.', $this->id, $this->currencyCode));
        }

        return $amount->minorUnits;
    }

    private function end(DateTimeImmutable $at): void
    {
        $this->state = SubscriptionState::Ended;
        $this->endedAt = $at;
        $this->awaitedOrder = null;
        $this->cancelAtPeriodEnd = false;
        $this->clearChange();
    }

    private function checkAwaited(string $orderNumber): void
    {
        if ($orderNumber !== $this->awaitedOrder) {
            throw SubscriptionRefused::notTheOrder($this->id, $orderNumber);
        }
    }
}
