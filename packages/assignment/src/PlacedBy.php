<?php

declare(strict_types=1);

namespace Phil\Assignment;

/**
 * How a placement was made: what the result tells the applicant and the organiser.
 */
enum PlacedBy: string
{
    /** The organiser forced the applicant into the slot, before anyone else. */
    case Forced = 'forced';

    /** A round of ranked choices gave the applicant the slot, at the round and rank the placement says. */
    case Round = 'round';

    /** A group seated, in draw order, before the optimisation of the people on their own. */
    case Group = 'group';

    /** The global optimisation gave the person the slot, for the lowest total cost of all ranks. */
    case Optimisation = 'optimisation';
}
