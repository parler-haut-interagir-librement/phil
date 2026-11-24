<?php

declare(strict_types=1);

namespace Phil\CommerceSubscriptionBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommerceSubscriptionBundle\Domain\ValueObject\DeliveryAddress;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The delivery address an order of a subscription was placed with, frozen: the subscriber who moves changes the
 * address of the next orders, never of this one.
 *
 * One copy per order, keyed by the order's number; it is erased with the subscriber's data, or some days after its
 * shipment left — after it was frozen when nothing was recorded as shipped.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_commerce_subscription_order_address')]
#[ORM\Index(columns: ['subscriber'])]
#[ORM\Index(columns: ['kept_since'])]
final class OrderAddress
{
    #[ORM\Column(name: 'shipped_at', nullable: true)]
    private ?DateTimeImmutable $shippedAt = null;

    /** When it was frozen, then when its shipment left: what its erasure counts from. */
    #[ORM\Column(name: 'kept_since')]
    private DateTimeImmutable $keptSince;

    /**
     * @param array<string, mixed> $address as `DeliveryAddress::toArray()` writes it
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'order_number', length: 64)]
        public readonly string $orderNumber,
        #[ORM\Column(name: 'subscription_id', length: 36, options: ['fixed' => true])]
        public readonly string $subscriptionId,
        #[ORM\Column(name: 'subscriber', length: 255)]
        public readonly string $subscriber,
        #[ORM\Column(name: 'address', type: 'json')]
        private readonly array $address,
        #[ORM\Column(name: 'frozen_at')]
        public readonly DateTimeImmutable $frozenAt,
    ) {
        $this->keptSince = $frozenAt;
    }

    public static function freeze(Subscription $subscription, DeliveryAddress $address, string $orderNumber, DateTimeImmutable $at): self
    {
        if ('' === trim($orderNumber) || \strlen($orderNumber) > 64) {
            throw InvalidValue::because('An order is numbered by 1 to 64 characters.');
        }

        return new self($orderNumber, $subscription->id, $subscription->subscriber, $address->toArray(), $at);
    }

    public function address(): DeliveryAddress
    {
        return DeliveryAddress::fromArray($this->address);
    }

    /**
     * Records that its shipment left at `$at`; recorded again, the latest moment counts.
     */
    public function shipped(DateTimeImmutable $at): void
    {
        if (!$this->shippedAt instanceof DateTimeImmutable || $at > $this->shippedAt) {
            $this->shippedAt = $at;
        }

        if ($at > $this->keptSince) {
            $this->keptSince = $at;
        }
    }

    public function shippedAt(): ?DateTimeImmutable
    {
        return $this->shippedAt;
    }

    /**
     * What its erasure counts from: its shipment, or its freezing when nothing was shipped.
     */
    public function keptSince(): DateTimeImmutable
    {
        return $this->keptSince;
    }
}
