<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Consignor;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\StatementPeriod;

/**
 * How often a consignor receives a statement: periods in UTC, a week from Monday, a calendar month or a calendar quarter.
 */
enum StatementFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    /**
     * The last whole period over at the instant: the week, month or quarter before the one the instant falls in.
     */
    public function lastClosedPeriod(DateTimeImmutable $at): StatementPeriod
    {
        $at = $at->setTimezone(new DateTimeZone('UTC'));
        $month = (int) $at->format('n');

        $end = match ($this) {
            self::Weekly => $at->modify('monday this week'),
            self::Monthly => $at->setDate((int) $at->format('Y'), $month, 1)->setTime(0, 0),
            self::Quarterly => $at->setDate((int) $at->format('Y'), $month - ($month - 1) % 3, 1)->setTime(0, 0),
        };

        $start = match ($this) {
            self::Weekly => $end->modify('-7 days'),
            self::Monthly => $end->modify('-1 month'),
            self::Quarterly => $end->modify('-3 months'),
        };

        return StatementPeriod::of($start, $end);
    }
}
