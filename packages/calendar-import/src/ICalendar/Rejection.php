<?php

declare(strict_types=1);

namespace Phil\CalendarImport\ICalendar;

/**
 * Why an event was refused alone, the other events of its calendar being read.
 */
enum Rejection: string
{
    /** A line of the event is not a name, parameters and a value, or a property it holds once appears twice. */
    case Malformed = 'malformed';
    case MissingUid = 'missing-uid';
    case MissingStart = 'missing-start';
    /** A date or a time that is not written as RFC 5545 writes them, does not exist, or ends before it starts. */
    case InvalidDate = 'invalid-date';
    /** A `TZID` neither PHP, nor the Windows names, nor a simple `VTIMEZONE` of the text resolves. */
    case UnknownTimeZone = 'unknown-time-zone';
    case InvalidDuration = 'invalid-duration';
    /** A rule, or added or removed dates, that `phil/recurrence` does not read. */
    case InvalidRecurrence = 'invalid-recurrence';
    /** A value outside what its property allows: a status, a sequence, a UID too long. */
    case InvalidValue = 'invalid-value';
    /** An event marked `CLASS:PRIVATE` or `CLASS:CONFIDENTIAL`: never imported to a public calendar. */
    case Private = 'private';
    /** A second event of the same UID and the same occurrence; the newest is kept. */
    case DuplicateUid = 'duplicate-uid';
    /** A form RFC 5545 allows that the reader does not follow, such as `RANGE=THISANDFUTURE`. */
    case Unsupported = 'unsupported';
}
