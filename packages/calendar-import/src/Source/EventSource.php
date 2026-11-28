<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Source;

use Phil\CalendarImport\Exception\SourceUnavailable;

/**
 * Where events come from: a source gives the events of a calendar, each with its UID and its version, or says that
 * nothing changed since the validators of its last answer. `IcsUrlSource` reads an iCalendar feed by its address;
 * another protocol is another implementation.
 */
interface EventSource
{
    /**
     * @param ?FetchValidators $validators what the source answered last, for a conditional read
     *
     * @throws SourceUnavailable when the source gives no calendar this time
     */
    public function fetch(CalendarSource $source, ?FetchValidators $validators = null): Fetched;
}
