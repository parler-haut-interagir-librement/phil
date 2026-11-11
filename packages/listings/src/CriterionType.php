<?php

declare(strict_types=1);

namespace Phil\Listings;

/**
 * How a criterion is stated, and so how two of them meet.
 */
enum CriterionType: string
{
    /** One or more values out of a set the application defines: a genre, a day of the week, a contract type. */
    case Choice = 'choice';

    /** An integer interval: a number of players, an age bracket, a salary band in whole units. */
    case Range = 'range';

    /** Yes or no: remote work accepted, beginners welcome. */
    case Flag = 'flag';
}
