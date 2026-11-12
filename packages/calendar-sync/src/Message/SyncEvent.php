<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Message;

/**
 * Asks for one of the site's events to be written into a connection, or removed from it, for a Messenger transport:
 * the identifier of the connection and the one of the event. The handler reads the event as it is then, through
 * `Contract\OutboundEvents`, so a message sent before a change writes the change.
 */
final readonly class SyncEvent
{
    public function __construct(
        public string $connectionId,
        public string $uid,
    ) {
    }
}
