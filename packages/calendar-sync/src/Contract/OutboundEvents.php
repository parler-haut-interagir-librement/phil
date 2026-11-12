<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Contract;

use Phil\CalendarSync\OutboundEvent;

/**
 * Where a message's handler finds the event it names: a message carries the identifier, the application gives the
 * event as it is when the message is handled.
 */
interface OutboundEvents
{
    /**
     * The event as the connection should show it, or `null` when it is gone and should be removed.
     */
    public function find(string $connectionId, string $uid): ?OutboundEvent;
}
