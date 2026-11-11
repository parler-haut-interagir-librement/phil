<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Infrastructure;

use Locale;
use Override;
use Phil\ReportingBundle\Application\Dashboard\ChartRendering;
use Phil\ReportingBundle\Application\Dashboard\Dashboards;
use Phil\ReportingBundle\Application\Exception\AccessDenied;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\ReportingBundle\Application\View\ChartDrawing;
use Phil\ReportingBundle\Application\View\MetricReading;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Infrastructure\Twig\Components\ChartComponent;
use Phil\ReportingBundle\Infrastructure\Twig\Components\DashboardComponent;
use Phil\ReportingBundle\Infrastructure\Twig\Components\LiveDashboardComponent;
use Phil\ReportingBundle\Infrastructure\Twig\Components\MetricComponent;
use Phil\ReportingBundle\Infrastructure\Twig\DashboardAccess;
use Phil\ReportingBundle\Tests\Unit\Fixture\OrdersSource;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(MetricComponent::class)]
#[CoversClass(ChartComponent::class)]
#[CoversClass(DashboardComponent::class)]
#[CoversClass(LiveDashboardComponent::class)]
#[CoversClass(DashboardAccess::class)]
#[CoversClass(AccessDenied::class)]
final class ComponentsTest extends TestCase
{
    private string $locale;

    #[Override]
    protected function setUp(): void
    {
        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
    }

    #[Override]
    protected function tearDown(): void
    {
        Locale::setDefault($this->locale);
    }

    public function testAMetric(): void
    {
        $orders = new OrdersSource();
        $component = new MetricComponent(Reporting::sources($orders), Reporting::periods(), new MetricReading());
        $component->source = 'orders';

        self::assertSame(['last_30_days', '', false, ''], [$component->period, $component->title, $component->compare, $component->locale]);
        self::assertSame('Orders', $component->getHeading());
        self::assertSame(30.0, $component->getMetric()->value);
        self::assertSame('30', $component->getText());
        self::assertNull($component->getChange());
        self::assertSame('2026-10-10', $orders->asked[0]->start->format('Y-m-d'));

        $component->period = 'this_month';
        $component->title = 'Commandes';
        $component->compare = true;
        $component->locale = 'en';
        self::assertSame('Commandes', $component->getHeading());
        self::assertSame(['direction' => 'down', 'percent' => '3.2%'], $component->getChange());
        self::assertSame(['2026-10-01', '2026-11-01'], array_map(static fn (Period $period): string => $period->start->format('Y-m-d'), \array_slice($orders->asked, -2)));

        $component->period = 'last_7_days';
        $component->locale = '';
        self::assertSame(['direction' => 'flat', 'percent' => '0 %'], $this->spaces($component->getChange()));
    }

    public function testAChart(): void
    {
        $component = new ChartComponent(Reporting::sources(), Reporting::periods(), new ChartDrawing(), new MetricReading());
        $component->source = 'visits';

        self::assertSame(['last_30_days', '', 'bar', 'svg', ''], [$component->period, $component->title, $component->chart, $component->render, $component->locale]);
        self::assertSame('Visits', $component->getHeading());
        self::assertSame(ChartRendering::Svg, $component->getRendering());
        self::assertSame(['Mon', 'Tue', 'Wed'], $component->getSet()->labels);
        self::assertStringContainsString('<title>Visits</title>', $component->getSvg());
        self::assertStringContainsString('<rect x=', $component->getSvg());
        self::assertSame(100, $component->getBars()[0]['bars'][0]['share']);
        self::assertSame('5,5', $component->number(5.5));
        self::assertSame('', $component->number(null));

        $component->title = 'Visites';
        $component->chart = 'line';
        $component->render = 'html';
        $component->locale = 'en';
        self::assertSame('Visites', $component->getHeading());
        self::assertSame(ChartRendering::Html, $component->getRendering());
        self::assertStringContainsString('<title>Visites</title>', $component->getSvg());
        self::assertStringNotContainsString('<rect x="', explode('<path', $component->getSvg())[1] ?? '');
        self::assertStringContainsString('<path d=', $component->getSvg());
        self::assertSame('1,234.5', $component->number(1234.5));

        $component->render = 'pie chart';
        $component->chart = 'radar';
        self::assertSame(ChartRendering::Svg, $component->getRendering());
        self::assertStringContainsString('<rect x=', explode('<g fill="#444444" text-anchor="middle">', $component->getSvg())[1] ?? '');
    }

    public function testADashboard(): void
    {
        $access = new DashboardAccess($this->dashboards());

        foreach ([new DashboardComponent($access, Reporting::periods()), new LiveDashboardComponent($access, Reporting::periods())] as $component) {
            $component->name = 'open';
            self::assertSame('', $component->period);
            self::assertSame('Open', $component->getDashboard()->title);
            self::assertSame(PeriodPreset::ThisMonth, $component->getPreset());
            self::assertSame('2026-11-01', $component->getRange()->start->format('Y-m-d'));

            $component->period = 'last_month';
            self::assertSame(PeriodPreset::LastMonth, $component->getPreset());
            self::assertSame('2026-10-01', $component->getRange()->start->format('Y-m-d'));
        }

        self::assertFalse(new DashboardComponent($access, Reporting::periods())->isLive());
        self::assertTrue(new LiveDashboardComponent($access, Reporting::periods())->isLive());
    }

    public function testARestrictedDashboard(): void
    {
        $granted = new DashboardAccess($this->dashboards(), $this->checker(true));
        self::assertSame('Admin', $granted->get('admin')->title);
        self::assertSame('Open', new DashboardAccess($this->dashboards(), $this->checker(false))->get('open')->title);

        foreach ([new DashboardAccess($this->dashboards(), $this->checker(false)), new DashboardAccess($this->dashboards())] as $access) {
            try {
                $access->get('admin');
                self::fail('Granted.');
            } catch (AccessDenied $accessDenied) {
                self::assertSame('Access to the dashboard "admin" is denied.', $accessDenied->getMessage());
            }
        }
    }

    private function dashboards(): Dashboards
    {
        return Dashboards::fromConfig([
            'open' => ['title' => 'Open', 'role' => null, 'period' => 'this_month', 'periods' => ['this_month', 'last_month'], 'widgets' => []],
            'admin' => ['title' => 'Admin', 'role' => 'ROLE_ADMIN', 'period' => 'this_month', 'periods' => ['this_month'], 'widgets' => []],
        ]);
    }

    private function checker(bool $granted): AuthorizationCheckerInterface
    {
        return new readonly class($granted) implements AuthorizationCheckerInterface {
            public function __construct(
                private bool $granted,
            ) {
            }

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return 'ROLE_ADMIN' === $attribute && $this->granted;
            }
        };
    }

    /**
     * @param array{direction: string, percent: string|null}|null $change
     *
     * @return array{direction: string, percent: string|null}|null
     */
    private function spaces(?array $change): ?array
    {
        return null === $change ? null : ['direction' => $change['direction'], 'percent' => str_replace(["\u{202f}", "\u{a0}"], ' ', (string) $change['percent'])];
    }
}
