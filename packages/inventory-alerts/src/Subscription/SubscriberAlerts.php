<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Subscription;

use DateTimeImmutable;
use Phil\InventoryAlerts\Contract\SubscriberNotifier;
use Phil\InventoryAlerts\Contract\SubscriptionStore;
use Phil\InventoryAlerts\Internal\Check;

/**
 * Sends the back-in-stock and price-drop alerts by waves, in the order of subscription, and ends each subscription
 * once its alert is sent. It keeps no state of its own.
 */
final readonly class SubscriberAlerts
{
    public function __construct(
        private SubscriptionStore $store,
        private SubscriberNotifier $notifier,
        private WavePolicy $policy = new WavePolicy(),
    ) {
    }

    /**
     * Tells the next wave of subscribers that a SKU is back in stock, sized by the units available. The application
     * calls it again later — after the first buyers had their chance — while units remain and the wave was full.
     *
     * @return Wave|null the wave sent, or null when no unit is available or nobody waits
     */
    public function restocked(string $sku, int $available, DateTimeImmutable $at): ?Wave
    {
        Check::code('SKU', $sku);
        $size = $this->policy->sizeFor($available);

        return $size < 1 ? null : $this->send(SubscriptionKind::BackInStock, $sku, $size, $at, null);
    }

    /**
     * Tells the subscribers whose threshold a new price is at or below, up to the maximum of a wave. The application
     * calls it again at once while the wave was full.
     *
     * @return Wave|null the wave sent, or null when nobody waits for this price
     */
    public function priceChanged(string $sku, Price $price, DateTimeImmutable $at): ?Wave
    {
        Check::code('SKU', $sku);

        return $this->send(SubscriptionKind::PriceDrop, $sku, $this->policy->maximum, $at, $price);
    }

    private function send(SubscriptionKind $kind, string $sku, int $size, DateTimeImmutable $at, ?Price $price): ?Wave
    {
        $subscriptions = [];

        foreach ($this->store->active($kind, $sku, $size, $price) as $subscription) {
            if ($this->accepts($subscription, $kind, $sku, $price)) {
                $subscriptions[] = $subscription;
            }
        }

        usort($subscriptions, static fn (Subscription $a, Subscription $b): int => [$a->subscribedAt, $a->id] <=> [$b->subscribedAt, $b->id]);
        $subscriptions = \array_slice($subscriptions, 0, $size);

        if ([] === $subscriptions) {
            return null;
        }

        $wave = new Wave($kind, $sku, $subscriptions, \count($subscriptions) === $size, $price);

        $this->notifier->notify($wave);
        $this->store->markNotified($wave->subscriptionIds(), $at);

        return $wave;
    }

    /**
     * Whether a subscription the store returned belongs in the wave: the library checks again what the store
     * filtered, and sorts again what it ordered.
     */
    private function accepts(Subscription $subscription, SubscriptionKind $kind, string $sku, ?Price $price): bool
    {
        if (!$subscription->isActive() || $subscription->kind !== $kind || $subscription->sku !== $sku) {
            return false;
        }

        return !$price instanceof Price || ($subscription->threshold instanceof Price && $price->isAtOrBelow($subscription->threshold));
    }
}
