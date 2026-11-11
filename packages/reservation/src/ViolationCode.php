<?php

declare(strict_types=1);

namespace Phil\Reservation;

/**
 * Why a booking is refused. The values are stable: an application keeps them and translates them.
 */
enum ViolationCode: string
{
    /** Fewer than one unit, or more units than the resource holds: `max`. */
    case Quantity = 'quantity';

    /** Asked for later than the notice allows: `minutes`. */
    case TooSoon = 'too_soon';

    /** Asked for further ahead than allowed: `minutes`. */
    case TooFarAhead = 'too_far_ahead';

    /** Shorter than the shortest booking: `minutes`. */
    case TooShort = 'too_short';

    /** Longer than the longest booking: `minutes`. */
    case TooLong = 'too_long';

    /** Not on the grid of starts and lengths: `minutes`. */
    case OffGrid = 'off_grid';

    /** Outside the hours the resource can be booked. */
    case Unavailable = 'unavailable';

    /** Taken: the resource has fewer free units than asked for at some moment: `free`. */
    case Full = 'full';
}
