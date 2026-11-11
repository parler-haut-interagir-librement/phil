<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

/**
 * Where an event is attended: the `eventAttendanceMode` of an `Event`.
 */
enum EventAttendanceMode: string
{
    case Offline = 'https://schema.org/OfflineEventAttendanceMode';
    case Online = 'https://schema.org/OnlineEventAttendanceMode';
    case Mixed = 'https://schema.org/MixedEventAttendanceMode';
}
