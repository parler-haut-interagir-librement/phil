<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

/**
 * The day on which a person born on 29 February has a birthday in a year without one: the jurisdiction says which.
 */
enum LeapDayRule: string
{
    /** The year is completed on 1 March, the day after 28 February: the default. */
    case FirstOfMarch = 'march-1';

    /** The year is completed on 28 February. */
    case TwentyEighthOfFebruary = 'february-28';
}
