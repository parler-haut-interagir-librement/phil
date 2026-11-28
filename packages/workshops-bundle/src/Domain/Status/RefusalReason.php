<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Status;

/**
 * Why a session refuses a registration. Each case is a message of the `phil_workshops` domain, `refusal.<value>`.
 */
enum RefusalReason: string
{
    /** The session is cancelled. */
    case Cancelled = 'cancelled';

    /** Registration has not opened yet. */
    case NotOpen = 'not_open';

    /** Registration has closed: at its closing moment, or at the start of the session when it has none. */
    case Closed = 'closed';

    /** More seats asked for than one registration may take, or than the session holds. */
    case TooManySeats = 'too_many_seats';

    /**
     * No seats free for it: every seat taken, or the free seats offered to, or asked for by, those on the session's
     * waiting list.
     */
    case Full = 'full';

    /** The participant already holds seats in the session, and asks for a place on its waiting list. */
    case Registered = 'registered';
}
