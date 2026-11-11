<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

/**
 * Whether an event takes place as announced: the `eventStatus` of an `Event`.
 */
enum EventStatus: string
{
    case Scheduled = 'https://schema.org/EventScheduled';
    case Cancelled = 'https://schema.org/EventCancelled';
    case Postponed = 'https://schema.org/EventPostponed';
    case Rescheduled = 'https://schema.org/EventRescheduled';
    case MovedOnline = 'https://schema.org/EventMovedOnline';
}
