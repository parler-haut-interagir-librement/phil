<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * A figure any bundle or the application measures — orders taken, members signed up, a conversion rate —, shown by
 * a metric widget. Every service implementing it is a source, found by its key.
 */
interface MetricSource
{
    /**
     * The source's key, unique among the metric sources: letters, digits, dots, dashes and underscores.
     */
    public static function key(): string;

    /**
     * The name the widget shows when its configuration gives none.
     */
    public function title(): string;

    /**
     * The figure over the period, its start included and its end excluded.
     */
    public function metric(Period $period): Metric;
}
