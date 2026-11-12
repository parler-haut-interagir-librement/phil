<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Report;

enum ReportCadence: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
}
