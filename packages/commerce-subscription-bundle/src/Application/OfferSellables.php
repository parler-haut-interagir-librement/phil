<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceSubscriptionBundle\Domain\Model\Gift;
use Phil\CommerceSubscriptionBundle\Domain\Model\Offer;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\GiftRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\OfferRepository;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;

/**
 * Tells the core what an offer sells, for three types of reference:
 *
 * - `offer:gold` is one period of the offer `gold`, at its current price, in its tax category — **while the offer is
 *   on sale**: a withdrawn offer stops new subscriptions, and no cart sells it.
 * - `subscription:<id>` is the next period of that subscription, at the price of its renewal tier — even withdrawn,
 *   since a withdrawn offer keeps renewing the subscriptions it has. Only a subscription that is not ended, not
 *   cancelled and awaits no order yet is sold: the line `Subscriptions::order()` places, never a second one. During
 *   a change at once, it is the new tier, which `ProrationOrderProcessor` prorates.
 * - `gift:<id>` is one period of the offer of that gift, a unit per period paid, while the gift awaits its order.
 */
final readonly class OfferSellables implements SellableResolver
{
    public const string TYPE = 'offer';

    public const string SUBSCRIPTION_TYPE = 'subscription';

    public const string GIFT_TYPE = 'gift';

    public function __construct(
        private OfferRepository $offers,
        private SubscriptionRepository $subscriptions,
        private ?GiftRepository $gifts = null,
    ) {
    }

    #[Override]
    public function supports(string $type): bool
    {
        return self::TYPE === $type || self::SUBSCRIPTION_TYPE === $type || (self::GIFT_TYPE === $type && $this->gifts instanceof GiftRepository);
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        $offer = match ($reference->type) {
            self::SUBSCRIPTION_TYPE => $this->subscriptionOffer($reference->id),
            self::GIFT_TYPE => $this->giftOffer($reference->id),
            default => $this->offerOnSale($reference->id),
        };

        return $offer instanceof Offer ? Sellable::of($reference, $offer->name(), $offer->price, $offer->taxCategory) : null;
    }

    private function subscriptionOffer(string $id): ?Offer
    {
        $subscription = $this->subscriptions->find($id);

        return $subscription instanceof Subscription && $subscription->awaitsAnOrder() ? $this->offers->find($subscription->renewalOfferCode()) : null;
    }

    private function giftOffer(string $id): ?Offer
    {
        $gift = $this->gifts?->find($id);

        return $gift instanceof Gift && $gift->awaitsAnOrder() ? $this->offers->find($gift->offerCode) : null;
    }

    private function offerOnSale(string $code): ?Offer
    {
        $offer = $this->offers->find($code);

        return $offer instanceof Offer && $offer->isActive() ? $offer : null;
    }
}
