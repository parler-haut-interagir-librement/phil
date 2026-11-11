<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Twig\Components;

use Phil\ReportingBundle\Application\Dashboard\Dashboard;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Infrastructure\Twig\DashboardAccess;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The dashboard of `PhilReporting:Dashboard` whose period changes without reloading the page,
 * `<twig:PhilReporting:LiveDashboard name="sales" />`. The period is in the address, so a link, a reload or a browser
 * without JavaScript shows the same one; the access is checked again at every rendering.
 */
#[AsLiveComponent(name: 'PhilReporting:LiveDashboard', template: '@PhilReporting/components/Dashboard.html.twig', exposePublicProps: false)]
final class LiveDashboardComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $name = '';

    #[LiveProp(writable: true, url: true)]
    public string $period = '';

    public function __construct(
        private readonly DashboardAccess $access,
        private readonly Periods $periods,
    ) {
    }

    public function getDashboard(): Dashboard
    {
        return $this->access->get($this->name);
    }

    public function getPreset(): PeriodPreset
    {
        return $this->getDashboard()->periodNamed($this->period);
    }

    public function getRange(): Period
    {
        return $this->periods->period($this->getPreset());
    }

    public function isLive(): bool
    {
        return true;
    }
}
