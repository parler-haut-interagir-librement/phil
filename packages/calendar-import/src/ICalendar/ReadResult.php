<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

use Phil\CalendarImport\SourceEvent;

/**
 * What the reader made of a text: the events it read, those it refused with their reasons, and the calendar's name.
 */
final readonly class ReadResult
{
    /**
     * @param list<SourceEvent>   $events   in the order of the text, one per key
     * @param list<RejectedEvent> $rejected in the order of the text
     * @param ?string             $name     the calendar's name (`X-WR-CALNAME`), plain text
     */
    public function __construct(
        public array $events,
        public array $rejected = [],
        public ?string $name = null,
    ) {
    }
}
