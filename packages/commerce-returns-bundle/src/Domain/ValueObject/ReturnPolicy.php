<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\CommerceReturnsBundle\Domain\Rma\DeliveryRefund;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * The shop's rules of returns: how long goods may come back and from when, the restocking fee, when the delivery is
 * given back, and how long an authorized return waits for its goods.
 */
final readonly class ReturnPolicy
{
    private function __construct(
        public int $windowDays,
        public bool $windowFromShipment,
        public int $restockingFee,
        public DeliveryRefund $deliveryRefund,
        public ?int $expiryDays,
    ) {
    }

    /**
     * @param int      $windowDays         how many days after an order is placed — or last shipped — its goods may come back
     * @param bool     $windowFromShipment whether the window counts from the order's last shipment, when one is known
     * @param int      $restockingFee      kept on the lines the shop is not at fault for, in parts per million
     * @param int|null $expiryDays         how many days an authorized return waits for its goods; `null` for ever
     */
    public static function of(int $windowDays = 30, bool $windowFromShipment = false, int $restockingFee = 0, DeliveryRefund $deliveryRefund = DeliveryRefund::ShopsFault, ?int $expiryDays = null): self
    {
        if ($windowDays < 0 || $restockingFee < 0 || $restockingFee > 1_000_000 || (null !== $expiryDays && $expiryDays < 1)) {
            throw InvalidValue::because('A return window is zero days or more, a restocking fee 0 to 1000000 parts per million, and an expiry one day or more.');
        }

        return new self($windowDays, $windowFromShipment, $restockingFee, $deliveryRefund, $expiryDays);
    }

    /**
     * Whether goods may still come back at that moment, the window counted from the moment given.
     */
    public function isOpen(DateTimeImmutable $from, DateTimeImmutable $at): bool
    {
        return $from->modify(\sprintf('+%d days', $this->windowDays)) >= $at;
    }
}
