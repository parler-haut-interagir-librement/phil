<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

use DateTimeImmutable;

/**
 * An order handed over, as the journal keeps it: where, when, by whom, and to whom when someone collected in the
 * customer's place — with the name the customer had designated, for the staff's check to be read later.
 */
final readonly class HandOver
{
    public function __construct(
        public string $order,
        public string $customer,
        public string $point,
        public string $station,
        public string $staff,
        public DateTimeImmutable $at,
        public ?string $collector = null,
        public ?string $designated = null,
    ) {
    }

    /**
     * Whether someone other than the customer collected.
     */
    public function byThirdParty(): bool
    {
        return null !== $this->collector;
    }

    /**
     * The same entry without the names of the collector nor of the designated person: what an erasure leaves.
     */
    public function withoutNames(): self
    {
        return new self($this->order, $this->customer, $this->point, $this->station, $this->staff, $this->at);
    }
}
