<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

/**
 * An event the reader refused, with its reason; the other events of its calendar were read.
 */
final readonly class RejectedEvent
{
    /**
     * @param ?string $uid the event's UID, when it could be read
     * @param ?string $key the key it would have had (see `SourceEvent::key()`), when its UID could be read: the
     *                     importer keeps the last version it read under that key rather than removing it
     */
    public function __construct(
        public ?string $uid,
        public ?string $key,
        public Rejection $reason,
        public string $message,
    ) {
    }
}
