<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Event;

/**
 * An offer accepted: the application gives the entry's participant the places it holds. It carries identifiers only: a listener reads the rest.
 */
final readonly class OfferAccepted
{
    public function __construct(
        public string $entryId,
        public string $waitlistId,
    ) {
    }
}
