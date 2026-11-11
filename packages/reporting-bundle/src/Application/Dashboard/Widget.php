<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Dashboard;

use Phil\SvgChart\ChartType;

/**
 * One tile of a dashboard: the source it shows, its title — the source's when empty —, whether a metric is compared
 * with the period before, and the kind and rendering of a chart.
 */
final readonly class Widget
{
    public function __construct(
        public WidgetKind $kind,
        public string $source,
        public string $title = '',
        public bool $compare = false,
        public ChartType $chart = ChartType::Bar,
        public ChartRendering $rendering = ChartRendering::Svg,
    ) {
    }
}
