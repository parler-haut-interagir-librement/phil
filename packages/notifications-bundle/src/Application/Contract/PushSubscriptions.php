<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Contract;

use Phil\NotificationsBundle\Application\WebPush\PushSubscription;

/**
 * The browsers each recipient subscribed for Web Push, one subscription per endpoint: an endpoint subscribed again,
 * by the same recipient or by another one signed in on the same browser, belongs to the last. A store keeps a bounded
 * number of subscriptions per recipient, the latest, so that a member cannot fill it.
 */
interface PushSubscriptions
{
    /**
     * Keeps the subscription for the recipient, replacing what its endpoint had, and forgets the recipient's oldest
     * subscriptions beyond the store's limit.
     */
    public function subscribe(string $recipient, PushSubscription $subscription): void;

    /**
     * The recipient's subscriptions, the latest first.
     *
     * @return list<PushSubscription>
     */
    public function of(string $recipient): array;

    /**
     * Forgets the endpoint if the recipient subscribed it, and says whether it did.
     */
    public function unsubscribe(string $recipient, string $endpoint): bool;

    /**
     * Forgets the endpoint, whoever subscribed it: what a push service no longer knows.
     */
    public function forget(string $endpoint): void;

    /**
     * Forgets every subscription of the recipient, when they leave.
     */
    public function forgetRecipient(string $recipient): void;
}
