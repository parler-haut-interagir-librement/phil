<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Memory;

use Override;
use Phil\NotificationsBundle\Application\Contract\PushSubscriptions;
use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The push subscriptions in memory, for tests and demonstrations: they last as long as the process, and are forgotten
 * when the container is reset between requests.
 */
final class InMemoryPushSubscriptions implements PushSubscriptions, ResetInterface
{
    public const int LIMIT = 10;

    /** @var array<string, array{string, PushSubscription}> by endpoint, the latest last */
    private array $subscriptions = [];

    /**
     * @param positive-int $limit how many subscriptions a recipient keeps at most
     */
    public function __construct(
        private readonly int $limit = self::LIMIT,
    ) {
    }

    #[Override]
    public function subscribe(string $recipient, PushSubscription $subscription): void
    {
        unset($this->subscriptions[$subscription->endpoint]);
        $this->subscriptions[$subscription->endpoint] = [$recipient, $subscription];

        foreach (\array_slice($this->of($recipient), $this->limit) as $older) {
            unset($this->subscriptions[$older->endpoint]);
        }
    }

    #[Override]
    public function of(string $recipient): array
    {
        $kept = [];

        foreach (array_reverse($this->subscriptions) as [$owner, $subscription]) {
            if ($owner === $recipient) {
                $kept[] = $subscription;
            }
        }

        return $kept;
    }

    #[Override]
    public function unsubscribe(string $recipient, string $endpoint): bool
    {
        if ($recipient !== ($this->subscriptions[$endpoint][0] ?? null)) {
            return false;
        }

        unset($this->subscriptions[$endpoint]);

        return true;
    }

    #[Override]
    public function forget(string $endpoint): void
    {
        unset($this->subscriptions[$endpoint]);
    }

    #[Override]
    public function forgetRecipient(string $recipient): void
    {
        $this->subscriptions = array_filter($this->subscriptions, static fn (array $kept): bool => $kept[0] !== $recipient);
    }

    #[Override]
    public function reset(): void
    {
        $this->subscriptions = [];
    }
}
