<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Psr\Clock\ClockInterface;

/**
 * The periods of the presets, from the clock's moment in the configured time zone.
 */
final readonly class Periods
{
    private DateTimeZone $timezone;

    public function __construct(
        private ClockInterface $clock,
        string $timezone = 'UTC',
    ) {
        $this->timezone = new DateTimeZone($timezone);
    }

    /**
     * @throws NotFound when no preset has that name
     */
    public function preset(string $name): PeriodPreset
    {
        return PeriodPreset::tryFrom($name) ?? throw NotFound::of('period', $name);
    }

    public function period(PeriodPreset $preset): Period
    {
        return $preset->period($this->now());
    }

    public function previous(PeriodPreset $preset): Period
    {
        return $preset->previous($this->now());
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->timezone);
    }
}
