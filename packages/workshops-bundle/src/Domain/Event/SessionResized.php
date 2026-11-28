<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Event;

/**
 * A session given another capacity: the session's waiting list, when there is one, offers the seats it frees or gives
 * back those it no longer has. It carries identifiers only: a listener reads the rest.
 */
final readonly class SessionResized
{
    public function __construct(
        public string $sessionId,
    ) {
    }
}
