<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Infrastructure\Twig\Components;

use Phil\ReportingBundle\Application\Dashboard\Dashboard;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\ReportingBundle\Application\Periods;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Infrastructure\Twig\DashboardAccess;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * A configured dashboard on a page, `<twig:PhilReporting:Dashboard name="sales" :period="app.request.query.get('period', '')" />`:
 * its title, the choice of its periods as a form that works without a script, and its widgets over the period chosen.
 */
#[AsTwigComponent(name: 'PhilReporting:Dashboard', template: '@PhilReporting/components/Dashboard.html.twig', exposePublicProps: false)]
final class DashboardComponent
{
    public string $name = '';

    /** The period chosen, among those the dashboard offers; the dashboard's own when empty or not offered. */
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
        return false;
    }
}
