<?php

declare(strict_types=1);

namespace Phil\Reservation;

/**
 * A reason a booking is refused, with the numbers its message needs.
 */
final readonly class Violation
{
    /**
     * @param array<string, int> $parameters
     */
    public function __construct(
        public ViolationCode $code,
        public array $parameters = [],
    ) {
    }
}
