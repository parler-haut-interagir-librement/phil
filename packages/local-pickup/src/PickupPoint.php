<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use Phil\LocalPickup\Internal\Check;
use Phil\Reservation\Bookable;

/**
 * A place an order is collected at — a store's counter or a convention booth —, tied to the stock location its goods
 * are taken from, as the logistics of the application names it.
 *
 * Its slots are those of a phil/reservation resource: the capacity of the resource is the number of orders one slot
 * takes, its hours and rules are when customers may come. An order is ready no sooner than the preparation time after
 * it is placed, and is held at the counter for the hold period. A point that stops taking orders — closed, or no longer
 * allowed — keeps serving the orders already placed: they carry their own copy of it.
 */
final readonly class PickupPoint
{
    /** The longest preparation time: thirty days. */
    public const int MAX_PREPARATION_MINUTES = 43_200;

    /** The longest hold period: ninety days. */
    public const int MAX_HOLD_HOURS = 2_160;

    /**
     * @param string $stockLocation the code of the stock location the goods are taken from
     * @param int    $slotMinutes   the length of a slot
     * @param bool   $accepting     whether the point takes new orders
     */
    public function __construct(
        public string $code,
        public string $name,
        public PickupPointKind $kind,
        public string $stockLocation,
        public string $address,
        public Bookable $slots,
        public int $slotMinutes = 30,
        public int $preparationMinutes = 120,
        public int $holdHours = 168,
        public bool $accepting = true,
    ) {
        Check::code('pickup point code', $code);
        Check::text('pickup point name', $name);
        Check::code('stock location', $stockLocation);
        Check::text('pickup point address', $address, multiline: true);
        Check::range('slot length', $slotMinutes, 1, 1_440);
        Check::range('preparation time', $preparationMinutes, 0, self::MAX_PREPARATION_MINUTES);
        Check::range('hold period', $holdHours, 1, self::MAX_HOLD_HOURS);
    }

    /**
     * The copy an order keeps of the point and of its hold period, as they are when it is placed.
     */
    public function agreed(): AgreedPickupPoint
    {
        return new AgreedPickupPoint($this->code, $this->name, $this->kind, $this->stockLocation, $this->address, $this->slots->timezone->getName(), $this->holdHours);
    }
}
