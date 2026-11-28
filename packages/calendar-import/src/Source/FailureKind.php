<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Source;

/**
 * Why a source gave no calendar.
 */
enum FailureKind: string
{
    /** The source could not be reached in time: no answer, a broken connection, a name that does not resolve. */
    case Unreachable = 'unreachable';
    /** The source answered with an error status. */
    case Status = 'status';
    /** The address, or a redirect, leads to a private network, or the redirects were too many. */
    case Refused = 'refused';
    /** The answer is larger than the bytes allowed. */
    case TooLarge = 'too-large';
    /** The answer is not a calendar the reader accepts. */
    case Invalid = 'invalid';
}
