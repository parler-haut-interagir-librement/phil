<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Dashboard;

use Phil\ReportingBundle\Application\PeriodPreset;

/**
 * A page of widgets over one period, which the viewer changes among the periods offered; restricted to a role when
 * one is named.
 */
final readonly class Dashboard
{
    /**
     * @param list<Widget>       $widgets
     * @param list<PeriodPreset> $periods the periods offered, in their order
     */
    public function __construct(
        public string $name,
        public string $title,
        public array $widgets,
        public PeriodPreset $period = PeriodPreset::Last30Days,
        public array $periods = [PeriodPreset::Last30Days],
        public ?string $role = null,
    ) {
    }

    /**
     * The preset of that name when the dashboard offers it, else the dashboard's own period: a name from the address
     * is anyone's.
     */
    public function periodNamed(string $name): PeriodPreset
    {
        $preset = PeriodPreset::tryFrom($name);

        return null !== $preset && \in_array($preset, $this->periods, true) ? $preset : $this->period;
    }
}
