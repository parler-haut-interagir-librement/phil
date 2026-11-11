<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Dashboard;

use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\SvgChart\ChartType;

/**
 * The dashboards of the configuration, by name.
 */
final readonly class Dashboards
{
    /**
     * @param array<string, Dashboard> $dashboards
     */
    public function __construct(
        private array $dashboards,
    ) {
    }

    /**
     * @param array<string, array{title: string, role: ?string, period: string, periods: list<string>, widgets: list<array{metric: ?string, series: ?string, title: string, compare: bool, chart: string, render: string}>}> $config
     */
    public static function fromConfig(array $config): self
    {
        $dashboards = [];

        foreach ($config as $name => $dashboard) {
            $widgets = [];

            foreach ($dashboard['widgets'] as $widget) {
                $widgets[] = new Widget(
                    null === $widget['metric'] ? WidgetKind::Chart : WidgetKind::Metric,
                    $widget['metric'] ?? (string) $widget['series'],
                    $widget['title'],
                    $widget['compare'],
                    ChartType::from($widget['chart']),
                    ChartRendering::from($widget['render']),
                );
            }

            $dashboards[$name] = new Dashboard(
                $name,
                $dashboard['title'],
                $widgets,
                PeriodPreset::from($dashboard['period']),
                array_map(PeriodPreset::from(...), $dashboard['periods']),
                $dashboard['role'],
            );
        }

        return new self($dashboards);
    }

    /**
     * @throws NotFound when no dashboard has that name
     */
    public function get(string $name): Dashboard
    {
        return $this->dashboards[$name] ?? throw NotFound::of('dashboard', $name);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->dashboards);
    }
}
