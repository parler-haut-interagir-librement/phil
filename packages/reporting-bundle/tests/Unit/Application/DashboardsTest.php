<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use Phil\ReportingBundle\Application\Dashboard\ChartRendering;
use Phil\ReportingBundle\Application\Dashboard\Dashboard;
use Phil\ReportingBundle\Application\Dashboard\Dashboards;
use Phil\ReportingBundle\Application\Dashboard\Widget;
use Phil\ReportingBundle\Application\Dashboard\WidgetKind;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\SvgChart\ChartType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dashboards::class)]
#[CoversClass(Dashboard::class)]
#[CoversClass(Widget::class)]
final class DashboardsTest extends TestCase
{
    public function testTheDashboardsOfTheConfiguration(): void
    {
        $dashboards = Dashboards::fromConfig([
            'sales' => [
                'title' => 'Sales',
                'role' => 'ROLE_ADMIN',
                'period' => 'this_month',
                'periods' => ['this_month', 'last_month'],
                'widgets' => [
                    ['metric' => 'orders', 'series' => null, 'title' => 'Orders', 'compare' => true, 'chart' => 'bar', 'render' => 'svg'],
                    ['metric' => null, 'series' => 'visits', 'title' => '', 'compare' => false, 'chart' => 'line', 'render' => 'html'],
                ],
            ],
            'empty' => ['title' => 'Empty', 'role' => null, 'period' => 'today', 'periods' => ['today'], 'widgets' => []],
        ]);

        self::assertSame(['sales', 'empty'], $dashboards->names());
        $sales = $dashboards->get('sales');
        self::assertSame(['sales', 'Sales', 'ROLE_ADMIN', PeriodPreset::ThisMonth, [PeriodPreset::ThisMonth, PeriodPreset::LastMonth]], [$sales->name, $sales->title, $sales->role, $sales->period, $sales->periods]);
        self::assertEquals([
            new Widget(WidgetKind::Metric, 'orders', 'Orders', true, ChartType::Bar, ChartRendering::Svg),
            new Widget(WidgetKind::Chart, 'visits', '', false, ChartType::Line, ChartRendering::Html),
        ], $sales->widgets);
        self::assertNull($dashboards->get('empty')->role);

        $this->expectExceptionObject(NotFound::of('dashboard', 'nope'));

        $dashboards->get('nope');
    }

    public function testThePeriodNamedFromTheAddress(): void
    {
        $dashboard = new Dashboard('d', 'D', [], PeriodPreset::ThisMonth, [PeriodPreset::ThisMonth, PeriodPreset::LastMonth]);

        self::assertSame(PeriodPreset::LastMonth, $dashboard->periodNamed('last_month'));
        self::assertSame(PeriodPreset::ThisMonth, $dashboard->periodNamed('this_year'));
        self::assertSame(PeriodPreset::ThisMonth, $dashboard->periodNamed('<script>'));
        self::assertSame(PeriodPreset::ThisMonth, $dashboard->periodNamed(''));

        $default = new Dashboard('d', 'D', []);
        self::assertSame([PeriodPreset::Last30Days, [PeriodPreset::Last30Days], null], [$default->period, $default->periods, $default->role]);

        $widget = new Widget(WidgetKind::Chart, 'visits');
        self::assertSame(['', false, ChartType::Bar, ChartRendering::Svg], [$widget->title, $widget->compare, $widget->chart, $widget->rendering]);
    }
}
