<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Event;

/**
 * An entry that left its waiting list, waiting or holding an offer. It carries identifiers only: a listener reads the rest.
 */
final readonly class EntryLeft
{
    public function __construct(
        public string $entryId,
        public string $waitlistId,
    ) {
    }
}
