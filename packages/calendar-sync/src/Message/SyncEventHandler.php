<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Message;

use Phil\CalendarSync\CalendarSync;
use Phil\CalendarSync\Contract\OutboundEvents;
use Phil\CalendarSync\OutboundEvent;

/**
 * Handles `SyncEvent`: writes the event the application gives, or removes it when the application gives none — it was
 * deleted, cancelled, or is no longer the member's. Register it as a Messenger handler, the tag
 * `messenger.message_handler`.
 */
final readonly class SyncEventHandler
{
    public function __construct(
        private CalendarSync $sync,
        private OutboundEvents $events,
    ) {
    }

    public function __invoke(SyncEvent $message): void
    {
        $event = $this->events->find($message->connectionId, $message->uid);

        if ($event instanceof OutboundEvent) {
            $this->sync->publish($message->connectionId, $event);

            return;
        }

        $this->sync->withdraw($message->connectionId, $message->uid);
    }
}
