<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Application;

use DateTimeImmutable;
use Phil\Address\Address;
use Phil\CommerceSubscriptionBundle\Application\Contract\DeliveryAddressRepository;
use Phil\CommerceSubscriptionBundle\Application\Contract\PhoneRequirement;
use Phil\CommerceSubscriptionBundle\Domain\Exception\SubscriptionRefused;
use Phil\CommerceSubscriptionBundle\Domain\Model\OrderAddress;
use Phil\CommerceSubscriptionBundle\Domain\Model\Subscription;
use Phil\CommerceSubscriptionBundle\Domain\Repository\SubscriptionRepository;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The delivery addresses of the subscriptions that ship something, and their copies frozen in each order.
 *
 * - **On the subscription**: `change()` keeps where the next shipments go; `remove()` forgets it. The telephone
 *   number is kept only when the carrier of the delivery method asks for one, and then it is required.
 * - **In each order**: `freeze()` copies the address an order is placed with, once per order; `Subscriptions` calls
 *   it for each order it places. A later change of address leaves the copy as it was.
 * - **Erased**: `purge()` forgets the address of a subscription ended and shipped to more than `$retentionDays` ago,
 *   and deletes the copies whose shipment left — or which were frozen, when nothing was shipped — more than
 *   `$retentionDays` ago. `erase()` forgets all of a subscriber's.
 *
 * Nothing is committed by `change()`, `remove()`, `freeze()` or `shipped()`; `erase()` and `purge()` write at once.
 */
final readonly class DeliveryAddresses
{
    /** The most batches one `purge()` runs, so that one run stays bounded. */
    private const int MAX_BATCHES = 1_000;

    /**
     * @param int $retentionDays how many days after the last shipment an address is erased
     */
    public function __construct(
        private SubscriptionRepository $subscriptions,
        private DeliveryAddressRepository $copies,
        private PhoneRequirement $phones,
        private int $retentionDays = 90,
    ) {
        if ($retentionDays < 1 || $retentionDays > 3_650) {
            throw InvalidValue::because(\sprintf('A delivery address is kept 1 to 3650 days after its last shipment; %d is not.', $retentionDays));
        }
    }

    /**
     * Keeps where the subscription's next shipments go.
     *
     * @param string|null $phone E.164; required when the method's carrier asks for it, and otherwise not kept
     */
    public function change(Subscription $subscription, Address $address, string $method, ?string $phone = null): DeliveryAddress
    {
        $delivery = DeliveryAddress::of($address, $method, $phone);

        if (!$this->phones->requiresPhone($method)) {
            $delivery = $delivery->withoutPhone();
        } elseif (null === $phone) {
            throw SubscriptionRefused::phoneRequired($method);
        }

        $subscription->shipTo($delivery);

        return $delivery;
    }

    public function remove(Subscription $subscription): void
    {
        $subscription->forgetDeliveryAddress();
    }

    /**
     * The address the subscription's next shipments go to, or `null`.
     */
    public function of(Subscription $subscription): ?DeliveryAddress
    {
        return $subscription->deliveryAddress();
    }

    /**
     * The address an order was placed with, or `null` when it was placed without one, or once erased.
     */
    public function ofOrder(string $orderNumber): ?DeliveryAddress
    {
        return $this->copies->find($orderNumber)?->address();
    }

    /**
     * Freezes the subscription's address in the order, or returns the copy already frozen in it; `null` when the
     * subscription has no address.
     */
    public function freeze(Subscription $subscription, string $orderNumber, DateTimeImmutable $at): ?OrderAddress
    {
        $copy = $this->copies->find($orderNumber);

        if ($copy instanceof OrderAddress) {
            return $copy;
        }

        $address = $subscription->deliveryAddress();

        if (!$address instanceof DeliveryAddress) {
            return null;
        }

        $copy = OrderAddress::freeze($subscription, $address, $orderNumber, $at);
        $this->copies->add($copy);

        return $copy;
    }

    /**
     * Records that the order's shipment left at `$at`, on its copy and on its subscription: the retention of both
     * counts from the last shipment. An order without a copy records nothing.
     */
    public function shipped(string $orderNumber, DateTimeImmutable $at): void
    {
        $copy = $this->copies->find($orderNumber);

        if (!$copy instanceof OrderAddress) {
            return;
        }

        $copy->shipped($at);
        $this->subscriptions->find($copy->subscriptionId)?->shipped($at);
    }

    /**
     * @return list<OrderAddress> the subscriber's copies, oldest first, the first `$limit`
     */
    public function copiesOf(string $subscriber, int $limit = 1_000): array
    {
        return $this->copies->ofSubscriber($subscriber, $limit);
    }

    /**
     * Erases every delivery address of the subscriber, on their subscriptions and in their orders.
     *
     * @return int how many were erased; 0 when run again
     */
    public function erase(string $subscriber): int
    {
        return $this->copies->erase($subscriber);
    }

    /**
     * Erases what has outlived the retention at `$at`, by batches of `$limit`, until a batch finds less than that.
     *
     * @return array{addresses: int, copies: int} how many were erased; zeros when run again
     */
    public function purge(DateTimeImmutable $at, int $limit = 500): array
    {
        if ($limit < 1) {
            throw InvalidValue::because('A purge reads batches of one or more.');
        }

        $cutoff = $at->modify(\sprintf('-%d days', $this->retentionDays));
        $erased = ['addresses' => 0, 'copies' => 0];

        for ($batch = 0; $batch < self::MAX_BATCHES; ++$batch) {
            $done = $this->copies->purge($cutoff, $limit);
            $erased['addresses'] += $done['addresses'];
            $erased['copies'] += $done['copies'];

            if ($done['addresses'] < $limit && $done['copies'] < $limit) {
                break;
            }
        }

        return $erased;
    }

    public function retentionDays(): int
    {
        return $this->retentionDays;
    }
}
