<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommerceSubscriptionBundle\Application\Contract\DeliveryAddressRepository;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Subscription\SubscriptionState;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;

class InMemoryDeliveryAddresses implements DeliveryAddressRepository
{
    /** @var array<string, OrderAddress> by order number */
    public array $copies = [];

    /** @var list<array{DateTimeImmutable, int}> the cutoff and limit of each purge batch */
    public array $purges = [];

    public function __construct(private readonly InMemorySubscriptions $subscriptions)
    {
    }

    #[Override]
    public function find(string $orderNumber): ?OrderAddress
    {
        return $this->copies[$orderNumber] ?? null;
    }

    #[Override]
    public function add(OrderAddress $copy): void
    {
        $this->copies[$copy->orderNumber] = $copy;
    }

    #[Override]
    public function ofSubscriber(string $subscriber, int $limit): array
    {
        return \array_slice(array_values(array_filter($this->copies, static fn (OrderAddress $copy): bool => $copy->subscriber === $subscriber)), 0, $limit);
    }

    #[Override]
    public function erase(string $subscriber): int
    {
        $erased = 0;

        foreach ($this->subscriptions->subscriptions as $subscription) {
            if ($subscription->subscriber === $subscriber && $subscription->forgetDeliveryAddress()) {
                ++$erased;
            }
        }

        foreach ($this->copies as $number => $copy) {
            if ($copy->subscriber === $subscriber) {
                unset($this->copies[$number]);
                ++$erased;
            }
        }

        return $erased;
    }

    #[Override]
    public function purge(DateTimeImmutable $cutoff, int $limit): array
    {
        $this->purges[] = [$cutoff, $limit];
        $addresses = 0;

        foreach ($this->subscriptions->subscriptions as $subscription) {
            if ($addresses < $limit && $this->expired($subscription, $cutoff)) {
                $subscription->forgetDeliveryAddress();
                ++$addresses;
            }
        }

        $copies = 0;

        foreach ($this->copies as $number => $copy) {
            if ($copies < $limit && $copy->keptSince() < $cutoff) {
                unset($this->copies[$number]);
                ++$copies;
            }
        }

        return ['addresses' => $addresses, 'copies' => $copies];
    }

    private function expired(Subscription $subscription, DateTimeImmutable $cutoff): bool
    {
        $ended = $subscription->endedAt();
        $shipped = $subscription->lastShippedAt();

        return SubscriptionState::Ended === $subscription->state()
            && $subscription->deliveryAddress() instanceof DeliveryAddress
            && $ended instanceof DateTimeImmutable && $ended < $cutoff
            && (!$shipped instanceof DateTimeImmutable || $shipped < $cutoff);
    }
}
