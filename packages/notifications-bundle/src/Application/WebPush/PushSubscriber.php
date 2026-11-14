<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\WebPush;

use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Application\Exception\InvalidPushSubscription;
use Phil\NotificationsBundle\Domain\Identifiers;

/**
 * Keeps what a recipient's browser sent when they turned Web Push on, and forgets it when they turn it off: the
 * subscription is read, its endpoint checked against the push services the site allows, then stored.
 */
final readonly class PushSubscriber
{
    /** The push services of the browsers people use: Chrome, Edge, Firefox, Safari, and those built on them. */
    public const array PUSH_SERVICES = [
        'fcm.googleapis.com',
        'push.services.mozilla.com',
        'notify.windows.com',
        'push.apple.com',
    ];

    /**
     * @param list<string> $services the hosts an endpoint may be on, or under; any public host when empty
     */
    public function __construct(
        private PushSubscriptions $subscriptions,
        private array $services = self::PUSH_SERVICES,
    ) {
    }

    /**
     * @throws InvalidPushSubscription when the JSON is not a subscription, or its endpoint is on no allowed service
     */
    public function subscribe(string $recipient, string $json): PushSubscription
    {
        $subscription = PushSubscription::fromJson($json);

        if (!$subscription->isOn($this->services)) {
            throw InvalidPushSubscription::because(\sprintf('The push service "%s" is not one this site sends to.', $subscription->host));
        }

        $this->subscriptions->subscribe(Identifiers::id($recipient, 'recipient'), $subscription);

        return $subscription;
    }

    /**
     * Forgets the endpoint the browser names, `{"endpoint": …}`, if the recipient subscribed it.
     *
     * @throws InvalidPushSubscription when the JSON names no endpoint
     */
    public function unsubscribe(string $recipient, string $json): bool
    {
        $data = \strlen($json) > PushSubscription::MAX_JSON ? null : json_decode($json, true, 4);

        if (!\is_array($data) || !\is_string($data['endpoint'] ?? null)) {
            throw InvalidPushSubscription::because('An unsubscription is a JSON object with an "endpoint".');
        }

        $endpoint = PushSubscription::canonical($data['endpoint']);

        return null !== $endpoint && $this->subscriptions->unsubscribe($recipient, $endpoint);
    }
}
