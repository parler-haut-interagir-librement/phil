<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Application\OrderModifier;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\ValueObject\PricingContext;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSubscriptionBundle\Domain\Exception\GiftRefused;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Gift\GiftRenewal;
use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\GiftRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\StableIdentifier;

/**
 * Subscriptions offered to someone else: the buyer pays, by an order of the core, and the code the payment issues
 * starts the subscription of whoever redeems it.
 *
 * The application keeps the loop, as for subscriptions: `buy()`, `order()`, a payment of that order, then `paid()`,
 * which returns the code to hand over — typed, or carried by a link the application builds —, or `paymentFailed()`.
 * The code is returned once and never stored; `reissueCode()` replaces a lost one. Nothing is committed here, and
 * the attempts at `redeem()` are the application's to limit, with Symfony's rate limiter.
 */
final readonly class Gifts
{
    /**
     * @param int $redeemWithinDays how long a paid gift's code redeems it
     */
    public function __construct(
        private OfferRepository $offers,
        private GiftRepository $gifts,
        private SubscriptionRepository $subscriptions,
        private OrderModifier $orders,
        private int $redeemWithinDays = 365,
        private ?FeatureAccess $access = null,
    ) {
    }

    /**
     * @param string $buyer the buyer's stable identifier, never an email address or a username
     */
    public function buy(string $buyer, string $offerCode, int $periods, GiftRenewal $renewal, DateTimeImmutable $at): Gift
    {
        $gift = Gift::buy($this->offer($offerCode), StableIdentifier::of($buyer, 'buyer'), $periods, $renewal, $at);
        $this->gifts->add($gift);

        return $gift;
    }

    /**
     * Places the order of the gift — the line `gift:<id>`, one unit per period, at the offer's current price — and
     * records it as awaited. The application stores the order and starts its payment.
     *
     * @param string|null $taxZone the buyer's tax zone, as the core's tax rates name it
     */
    public function order(Gift $gift, string $orderNumber, ?string $taxZone, DateTimeImmutable $at): Order
    {
        if (!$gift->awaitsAnOrder()) {
            throw GiftRefused::state($gift->id, $gift->state(), 'be ordered again');
        }

        $reference = SellableReference::of(OfferSellables::GIFT_TYPE, $gift->id);
        $order = Order::open($orderNumber, $gift->currency());
        $this->orders->add($order, $reference, $gift->periods, PricingContext::of($gift->periods, $at));
        $this->orders->changeTaxZone($order, $taxZone);
        $this->orders->place($order, $at);

        // One period's share of what the line charges, rounded down: what a later change of tier credits from.
        $gift->awaitPayment($orderNumber, OrderedLine::base($order, $reference)->split($gift->periods)[$gift->periods - 1]);

        return $order;
    }

    /**
     * The order was paid: returns the code that redeems the gift, shown once.
     */
    public function paid(Gift $gift, string $orderNumber, DateTimeImmutable $at): string
    {
        return $gift->paid($orderNumber, $at->modify(\sprintf('+%d days', $this->redeemWithinDays)));
    }

    public function paymentFailed(Gift $gift, string $orderNumber): void
    {
        $gift->paymentFailed($orderNumber);
    }

    public function reissueCode(Gift $gift): string
    {
        return $gift->reissueCode();
    }

    public function cancel(Gift $gift, DateTimeImmutable $at): void
    {
        $gift->cancel($at);
    }

    /**
     * Starts the beneficiary's subscription with the code: active at once for the periods paid, renewed at the
     * buyer's charge — the buyer is its `payer()` — or cancelled at its term.
     *
     * A code unknown, malformed, already used, replaced or expired is refused the same way.
     *
     * @param string $beneficiary the beneficiary's stable identifier, never an email address or a username
     */
    public function redeem(string $code, string $beneficiary, DateTimeImmutable $at): Subscription
    {
        $hash = Gift::hashOf($code);
        $gift = null === $hash ? null : $this->gifts->findByCodeHash($hash);

        if (!$gift instanceof Gift || !$gift->isRedeemableWith($code, $at)) {
            throw GiftRefused::notRedeemable();
        }

        $identifier = StableIdentifier::of($beneficiary, 'beneficiary');
        $payer = GiftRenewal::BuyerPays === $gift->renewal ? $gift->buyer : null;
        $subscription = Subscription::fromGift($this->offer($gift->offerCode), $identifier, $payer, $gift->periods, $gift->periodAmount(), $at);
        $gift->redeem($code, $identifier, $subscription, $at);
        $this->subscriptions->add($subscription);
        $this->access?->forget($subscription->subscriber);

        return $subscription;
    }

    private function offer(string $code): Offer
    {
        return $this->offers->find($code) ?? throw SubscriptionRefused::unknownOffer($code);
    }
}
