<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application;

use DateTimeImmutable;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The periods a dashboard or a schedule names, counted in whole days of the configured time zone: a day, the last 7
 * or 30 days with today, a calendar month, the last 12 months with this one, a calendar year.
 */
enum PeriodPreset: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last_7_days';
    case Last30Days = 'last_30_days';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case Last12Months = 'last_12_months';
    case ThisYear = 'this_year';
    case LastYear = 'last_year';

    /**
     * The period around the moment, in the moment's time zone.
     */
    public function period(DateTimeImmutable $now): Period
    {
        $today = $now->setTime(0, 0);
        $month = $today->modify('first day of this month');
        $year = $today->setDate((int) $today->format('Y'), 1, 1);

        return match ($this) {
            self::Today => new Period($today, $today->modify('+1 day')),
            self::Yesterday => new Period($today->modify('-1 day'), $today),
            self::Last7Days => new Period($today->modify('-6 days'), $today->modify('+1 day')),
            self::Last30Days => new Period($today->modify('-29 days'), $today->modify('+1 day')),
            self::ThisMonth => new Period($month, $month->modify('+1 month')),
            self::LastMonth => new Period($month->modify('-1 month'), $month),
            self::Last12Months => new Period($month->modify('-11 months'), $month->modify('+1 month')),
            self::ThisYear => new Period($year, $year->modify('+1 year')),
            self::LastYear => new Period($year->modify('-1 year'), $year),
        };
    }

    /**
     * The period of the same length just before, which a metric is compared with: the day before, the 7 or 30 days
     * before, the month, the 12 months or the year before.
     */
    public function previous(DateTimeImmutable $now): Period
    {
        $period = $this->period($now);

        return new Period($period->start->modify('-'.$this->length()), $period->start);
    }

    private function length(): string
    {
        return match ($this) {
            self::Today, self::Yesterday => '1 day',
            self::Last7Days => '7 days',
            self::Last30Days => '30 days',
            self::ThisMonth, self::LastMonth => '1 month',
            self::Last12Months => '12 months',
            self::ThisYear, self::LastYear => '1 year',
        };
    }
}
