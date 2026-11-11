<?php

declare(strict_types=1);

namespace Phil\Recurrence;

/**
 * How often a rule repeats, before its interval: the periods it walks through.
 */
enum Frequency: string
{
    case Yearly = 'YEARLY';
    case Monthly = 'MONTHLY';
    case Weekly = 'WEEKLY';
    case Daily = 'DAILY';
}
