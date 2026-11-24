<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Infrastructure\Privacy;

use DateTimeInterface;
use Override;
use Phil\CommerceSubscriptionBundle\Application\DeliveryAddresses;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\PrivacyBundle\Application\Contract\PersonalDataEraser;
use Phil\PrivacyBundle\Application\Contract\PersonalDataExporter;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use Phil\PrivacyBundle\Domain\Subject\ModuleName;

/**
 * The delivery addresses' part of an access or erasure request, the data subject being the subscriber's identifier:
 * the address of each of their subscriptions, and the copy frozen in each of their orders. The erasure forgets the
 * first and deletes the second; run again, it finds nothing and reports the same effects.
 */
final readonly class DeliveryAddressPrivacy implements PersonalDataExporter, PersonalDataEraser
{
    public const string MODULE = 'commerce_subscription.delivery_addresses';

    public function __construct(
        private SubscriptionRepository $subscriptions,
        private DeliveryAddresses $addresses,
    ) {
    }

    #[Override]
    public function module(): ModuleName
    {
        return ModuleName::fromString(self::MODULE);
    }

    #[Override]
    public function export(DataSubject $subject): array
    {
        $subscriptions = [];

        foreach ($this->subscriptions->forSubscriber($subject->value) as $subscription) {
            $address = $subscription->deliveryAddress();

            if ($address instanceof DeliveryAddress) {
                $subscriptions[] = ['subscription' => $subscription->id, ...$address->toArray(), 'last_shipped_at' => $this->lastShipped($subscription)];
            }
        }

        $orders = array_map(static fn (OrderAddress $copy): array => [
            'order' => $copy->orderNumber,
            'subscription' => $copy->subscriptionId,
            ...$copy->address()->toArray(),
            'frozen_at' => $copy->frozenAt->format(DateTimeInterface::ATOM),
            'shipped_at' => $copy->shippedAt()?->format(DateTimeInterface::ATOM),
        ], $this->addresses->copiesOf($subject->value));

        if ([] === $subscriptions && [] === $orders) {
            return [];
        }

        return ['subscriptions' => $subscriptions, 'orders' => $orders];
    }

    #[Override]
    public function erase(DataSubject $subject): ErasureReport
    {
        $this->addresses->erase($subject->value);

        return ErasureReport::of(self::MODULE, [
            'phil_commerce_subscription.delivery_address' => ErasureEffect::Redacted,
            'phil_commerce_subscription_order_address' => ErasureEffect::Deleted,
        ]);
    }

    private function lastShipped(Subscription $subscription): ?string
    {
        return $subscription->lastShippedAt()?->format(DateTimeInterface::ATOM);
    }
}
