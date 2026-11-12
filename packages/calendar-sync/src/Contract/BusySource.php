<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Contract;

use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\SyncWindow;

/**
 * An adapter that reads when someone is taken from a calendar of theirs: busy periods only, never what fills them.
 */
interface BusySource
{
    /**
     * The name connections give to use it: `ics`, `caldav`, `google`, `microsoft`.
     */
    public function name(): string;

    /**
     * The busy periods that overlap the window, in any order; `CalendarSync` cuts them to it and joins them.
     *
     * @return iterable<BusyPeriod>
     *
     * @throws ProviderFailed      when the service refuses or does not answer
     * @throws InvalidCalendarData when its answer cannot be read
     */
    public function busy(CalendarConnection $connection, Credentials $credentials, SyncWindow $window): iterable;
}
