<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use DateTimeImmutable;
use Phil\LocalPickup\Contract\ArrivalNotifier;
use Phil\LocalPickup\Contract\PickupRepository;
use Phil\LocalPickup\Contract\ReadyNoticeDispatcher;
use Phil\LocalPickup\Exception\PickupRefused;
use Phil\LocalPickup\Exception\UnknownPickup;
use Phil\Reservation\Interval;

/**
 * The life of a pickup up to the counter: placed in a slot, made ready, announced, and the customer's arrival.
 *
 * **What the customer agreed to stays.** Placing copies the point — its name, address, stock location and hold period
 * — onto the pickup; a point that changes, closes or is no longer allowed afterwards changes nothing for the orders
 * already placed. Only placing reads whether a point takes orders.
 *
 * The service keeps no state between calls.
 */
final readonly class Pickups
{
    public function __construct(
        private PickupRepository $pickups,
        private PickupSlots $slots,
        private ReadyNoticeDispatcher $notices,
        private ?ArrivalNotifier $arrivals = null,
    ) {
    }

    /**
     * Books the slot for a placed order, at the point as it is now.
     *
     * @param ?string $collector the name of the person the customer designates to collect in their place
     *
     * @throws PickupRefused when the point takes no order, the order has a pickup already, or the slot is refused
     */
    public function place(string $order, string $customer, PickupPoint $point, Interval $slot, DateTimeImmutable $now, ?string $collector = null): Pickup
    {
        if (!$point->accepting) {
            throw PickupRefused::pointClosed($point->code);
        }

        if ($this->pickups->find($order) instanceof Pickup) {
            throw PickupRefused::alreadyPlaced($order);
        }

        $decision = $this->slots->check($point, $slot, $now);

        if (!$decision->allowed()) {
            throw PickupRefused::slot($order, $decision->violations);
        }

        $pickup = Pickup::place($order, $customer, $point->agreed(), $slot, $now, $collector);
        $this->pickups->save($pickup);

        return $pickup;
    }

    /**
     * Marks the order prepared, and asks for its notice: the message carries the order number, and the worker builds the
     * notice and its code.
     *
     * @throws UnknownPickup when no pickup is kept for the order
     * @throws PickupRefused when it is not being prepared
     */
    public function ready(string $order, DateTimeImmutable $now): Pickup
    {
        $pickup = $this->get($order);
        $pickup->ready($now);

        $this->pickups->save($pickup);
        $this->notices->dispatch($order);

        return $pickup;
    }

    /**
     * Forgets the current code and sends a new notice with a new one: after the code was locked, or lost.
     *
     * @throws UnknownPickup when no pickup is kept for the order
     * @throws PickupRefused when it is not ready
     */
    public function resend(string $order): void
    {
        $pickup = $this->get($order);
        $pickup->resetCode();

        $this->pickups->save($pickup);
        $this->notices->dispatch($order);
    }

    /**
     * Records the customer's arrival, automatic or by their own "I am here", and tells the staff on the first signal
     * only. Whether the customer still shares a location or any other permission plays no part.
     *
     * @return bool whether this signal was the first
     *
     * @throws UnknownPickup when no pickup is kept for the order
     * @throws PickupRefused when it is collected or expired
     */
    public function arrive(string $order, ArrivalSource $source, DateTimeImmutable $now): bool
    {
        $pickup = $this->get($order);

        if (!$pickup->arrive($source, $now)) {
            return false;
        }

        $this->pickups->save($pickup);
        $this->arrivals?->arrived($pickup);

        return true;
    }

    /**
     * @throws UnknownPickup when no pickup is kept for the order
     */
    public function get(string $order): Pickup
    {
        return $this->pickups->find($order) ?? throw UnknownPickup::forOrder($order);
    }
}
