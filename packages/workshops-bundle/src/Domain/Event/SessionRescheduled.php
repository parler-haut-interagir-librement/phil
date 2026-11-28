<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Event;

/**
 * A session moved to another span, its registrations kept: the application tells their participants. It carries
 * identifiers only: a listener reads the rest.
 */
final readonly class SessionRescheduled
{
    public function __construct(
        public string $sessionId,
    ) {
    }
}
