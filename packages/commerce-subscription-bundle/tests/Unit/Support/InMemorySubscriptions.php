<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;

final class InMemorySubscriptions implements SubscriptionRepository
{
    /** @var list<Subscription> */
    public array $subscriptions = [];

    /** @var array<string, int> how many times each subscriber's subscriptions were read */
    public array $reads = [];

    #[Override]
    public function find(string $id): ?Subscription
    {
        return array_find($this->subscriptions, static fn (Subscription $subscription): bool => $subscription->id === $id);
    }

    #[Override]
    public function forSubscriber(string $subscriber): array
    {
        $this->reads[$subscriber] = ($this->reads[$subscriber] ?? 0) + 1;

        return array_values(array_filter($this->subscriptions, static fn (Subscription $subscription): bool => $subscription->subscriber === $subscriber));
    }

    #[Override]
    public function forSubscribers(array $subscribers): array
    {
        foreach ($subscribers as $subscriber) {
            $this->reads[$subscriber] = ($this->reads[$subscriber] ?? 0) + 1;
        }

        return array_values(array_filter($this->subscriptions, static fn (Subscription $subscription): bool => \in_array($subscription->subscriber, $subscribers, true)));
    }

    #[Override]
    public function periodOverBy(DateTimeImmutable $at, ?int $limit = null): array
    {
        return \array_slice(array_values(array_filter($this->subscriptions, static fn (Subscription $subscription): bool => \in_array($subscription->state(), [SubscriptionState::Trialing, SubscriptionState::Active, SubscriptionState::PastDue], true)
            && $subscription->periodEnd() <= $at)), 0, $limit);
    }

    #[Override]
    public function pendingWithoutOrder(?int $limit = null): array
    {
        return \array_slice(array_values(array_filter($this->subscriptions, static fn (Subscription $subscription): bool => SubscriptionState::Pending === $subscription->state() && null === $subscription->awaitedOrder())), 0, $limit);
    }

    #[Override]
    public function pendingCreatedBefore(DateTimeImmutable $before, ?int $limit = null): array
    {
        return \array_slice(array_values(array_filter($this->subscriptions, static fn (Subscription $subscription): bool => SubscriptionState::Pending === $subscription->state() && $subscription->createdAt < $before)), 0, $limit);
    }

    #[Override]
    public function add(Subscription $subscription): void
    {
        $this->subscriptions[] = $subscription;
    }
}
