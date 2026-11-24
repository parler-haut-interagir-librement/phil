<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Subscription;

/**
 * The subscribers told together that a SKU is back in stock or that its price fell, in the order they subscribed.
 *
 * A wave says what happened, never how many units are left: an alert never presses with a scarcity.
 */
final readonly class Wave
{
    /**
     * @param non-empty-list<Subscription> $subscriptions
     */
    public function __construct(
        public SubscriptionKind $kind,
        public string $sku,
        public array $subscriptions,
        public bool $full,
        public ?Price $price = null,
    ) {
    }

    /**
     * The identifiers of the subscriptions, for messages put on a queue.
     *
     * @return non-empty-list<string>
     */
    public function subscriptionIds(): array
    {
        return array_map(static fn (Subscription $subscription): string => $subscription->id, $this->subscriptions);
    }
}
