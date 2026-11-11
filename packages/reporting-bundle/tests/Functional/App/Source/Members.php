<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Functional\App\Source;

use Override;
use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Domain\Format\MetricFormat;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * A metric of the application: the share of members who renewed, higher this month than the last.
 */
final class Members implements MetricSource
{
    #[Override]
    public static function key(): string
    {
        return 'renewals';
    }

    #[Override]
    public function title(): string
    {
        return 'Renewals';
    }

    #[Override]
    public function metric(Period $period): Metric
    {
        return new Metric('2026-11' === $period->start->format('Y-m') ? 0.6 : 0.5, MetricFormat::Percent);
    }
}
