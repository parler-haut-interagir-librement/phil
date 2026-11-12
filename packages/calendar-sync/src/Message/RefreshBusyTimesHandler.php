<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Message;

use Phil\CalendarSync\CalendarSync;

/**
 * Handles `RefreshBusyTimes`: register it as a Messenger handler, the tag `messenger.message_handler`.
 */
final readonly class RefreshBusyTimesHandler
{
    public function __construct(
        private CalendarSync $sync,
    ) {
    }

    public function __invoke(RefreshBusyTimes $message): void
    {
        $this->sync->refreshBusy($message->connectionId, $message->window());
    }
}
