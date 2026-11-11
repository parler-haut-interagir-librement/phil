<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Dashboard;

/**
 * What a widget shows: one figure of a metric source, or the series of a series source.
 */
enum WidgetKind: string
{
    case Metric = 'metric';
    case Chart = 'chart';
}
