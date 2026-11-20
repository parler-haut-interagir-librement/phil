<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Training;

/**
 * The step the training reports cut their period by, from the period's start: a month, three months or a year.
 */
enum TrainingStep: string
{
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /**
     * The step as `Period::split()` reads it.
     */
    public function interval(): string
    {
        return match ($this) {
            self::Month => '1 month',
            self::Quarter => '3 months',
            self::Year => '1 year',
        };
    }
}
