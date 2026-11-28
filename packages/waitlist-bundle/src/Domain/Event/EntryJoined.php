<?php

declare(strict_types=1);

namespace Phil\WaitlistBundle\Domain\Event;

/**
 * An entry that joined a waiting list. It carries identifiers only: a listener reads the rest.
 */
final readonly class EntryJoined
{
    public function __construct(
        public string $entryId,
        public string $waitlistId,
    ) {
    }
}
