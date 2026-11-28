<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Event;

/**
 * An offer declined: its places go to the next entries. It carries identifiers only: a listener reads the rest.
 */
final readonly class OfferDeclined
{
    public function __construct(
        public string $entryId,
        public string $waitlistId,
    ) {
    }
}
