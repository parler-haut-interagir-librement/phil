<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Delivery;

/**
 * What `DeliveryDedupe::begin()` gives: a duplicate, or a reservation that only the implementation that made it reads,
 * handed back to `complete()` or `release()`.
 */
final readonly class DedupeTicket
{
    private function __construct(
        public bool $duplicate,
        public ?object $reservation,
    ) {
    }

    public static function duplicate(): self
    {
        return new self(true, null);
    }

    public static function reserved(object $reservation): self
    {
        return new self(false, $reservation);
    }
}
