<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Event;

/**
 * A session cancelled: it takes no more registrations, and its registrations stay as they were for the application
 * to tell their participants. It carries identifiers only: a listener reads the rest.
 */
final readonly class SessionCancelled
{
    public function __construct(
        public string $sessionId,
        public string $workshopId,
    ) {
    }
}
