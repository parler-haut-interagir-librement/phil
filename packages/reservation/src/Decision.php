<?php

declare(strict_types=1);

namespace Phil\Reservation;

/**
 * What a resource answers to a booking request: the reasons it refuses it, none when it accepts it, and the bookings
 * the request meets, accepted or not.
 */
final readonly class Decision
{
    /**
     * @param list<Violation> $violations
     * @param list<string>    $conflicts  the identifiers of the bookings whose span, buffers included, meets the request's
     */
    public function __construct(
        public array $violations = [],
        public array $conflicts = [],
    ) {
    }

    public function allowed(): bool
    {
        return [] === $this->violations;
    }

    public function has(ViolationCode $code): bool
    {
        return array_any($this->violations, static fn (Violation $violation): bool => $code === $violation->code);
    }
}
