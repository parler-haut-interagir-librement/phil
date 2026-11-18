<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Event;

/**
 * A cmi5 statement was accepted from an assignable unit, or issued by the platform, and handed to the learning record store. Identifiers only: a listener reads the rest from the stores.
 */
final readonly class StatementRecorded
{
    public function __construct(
        public string $statement,
        public string $session,
        public string $verb,
    ) {
    }
}
