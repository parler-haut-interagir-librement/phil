<?php

declare(strict_types=1);

namespace Phil\Reservation;

use Phil\Reservation\Exception\InvalidDefinition;

/**
 * A booking that holds units of a resource for a span: what a new request is checked against.
 */
final readonly class Booking
{
    /**
     * @throws InvalidDefinition when the identifier is empty or the quantity below one
     */
    public function __construct(
        public string $id,
        public Interval $interval,
        public int $quantity = 1,
    ) {
        if ('' === $id) {
            throw InvalidDefinition::because('A booking has an identifier.');
        }

        if ($quantity < 1) {
            throw InvalidDefinition::because(\sprintf('A booking holds at least one unit, not %d.', $quantity));
        }
    }
}
