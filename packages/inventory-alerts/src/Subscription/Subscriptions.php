<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Subscription;

use DateTimeImmutable;
use Phil\EmailAddress\EmailAddress;
use Phil\InventoryAlerts\Contract\SubscriptionStore;

/**
 * Subscribing and unsubscribing: a subscription is made only by a customer's explicit request, at most one active per
 * address, SKU and kind, and ended in one click by the link of its alert.
 */
final readonly class Subscriptions
{
    public function __construct(
        private SubscriptionStore $store,
        private UnsubscribeTokens $tokens,
    ) {
    }

    /**
     * Subscribes an address to a back-in-stock alert of a SKU; the active subscription it already has is returned
     * as is.
     */
    public function subscribeBackInStock(string $sku, EmailAddress $email, DateTimeImmutable $at): Subscription
    {
        return $this->store->findActive(SubscriptionKind::BackInStock, $sku, $email)
            ?? $this->add(new Subscription(Subscription::newId(), SubscriptionKind::BackInStock, $sku, $email, $at));
    }

    /**
     * Subscribes an address to an alert when the price of a SKU falls to a threshold or below; an active
     * subscription it already has keeps its place and takes the new threshold.
     */
    public function subscribePriceDrop(string $sku, EmailAddress $email, Price $threshold, DateTimeImmutable $at): Subscription
    {
        $existing = $this->store->findActive(SubscriptionKind::PriceDrop, $sku, $email);

        return $this->add($existing instanceof Subscription
            ? new Subscription($existing->id, SubscriptionKind::PriceDrop, $sku, $email, $existing->subscribedAt, $threshold)
            : new Subscription(Subscription::newId(), SubscriptionKind::PriceDrop, $sku, $email, $at, $threshold));
    }

    /**
     * The token of the one-click unsubscription link of a subscription.
     */
    public function unsubscribeToken(Subscription $subscription): string
    {
        return $this->tokens->issue($subscription->id);
    }

    /**
     * Ends the subscription of a token and deletes it, address included. True for a valid token, even when the
     * subscription was already gone; false for a token this application did not issue.
     */
    public function unsubscribe(string $token): bool
    {
        $id = $this->tokens->verify($token);

        if (null === $id) {
            return false;
        }

        $this->store->delete($id);

        return true;
    }

    /**
     * Deletes the subscriptions notified before a time — once their alert is delivered, nothing keeps the address.
     */
    public function purgeNotified(DateTimeImmutable $before): int
    {
        return $this->store->deleteNotifiedBefore($before);
    }

    private function add(Subscription $subscription): Subscription
    {
        $this->store->save($subscription);

        return $subscription;
    }
}
