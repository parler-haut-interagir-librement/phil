<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Contract;

use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\SyncWindow;

/**
 * Where the application keeps the busy periods read from a connection, for its bookings to avoid them.
 */
interface BusyStore
{
    /**
     * Replaces what the connection had within the window by these periods, in order and joined.
     *
     * @param list<BusyPeriod> $periods
     */
    public function replace(string $connectionId, SyncWindow $window, array $periods): void;
}
