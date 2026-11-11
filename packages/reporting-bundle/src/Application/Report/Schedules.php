<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\PeriodPreset;
use Phil\ReportingBundle\Application\Periods;

/**
 * The schedules of the configuration, by name, and the job each one asks for at the current moment.
 */
final readonly class Schedules
{
    /**
     * @param array<string, ReportSchedule> $schedules
     */
    public function __construct(
        private array $schedules,
        private Periods $periods,
    ) {
    }

    /**
     * @param array<string, array{report: string, format: string, every: string, from: ?string, period: string, delivery: ?string, recipients: list<string>, parameters: array<string, bool|float|int|string>}> $config
     */
    public static function fromConfig(array $config, Periods $periods): self
    {
        $schedules = [];

        foreach ($config as $name => $schedule) {
            $schedules[$name] = new ReportSchedule(
                $name,
                $schedule['report'],
                $schedule['format'],
                $schedule['every'],
                $schedule['from'],
                PeriodPreset::from($schedule['period']),
                $schedule['delivery'],
                $schedule['recipients'],
                $schedule['parameters'],
            );
        }

        return new self($schedules, $periods);
    }

    /**
     * @return array<string, ReportSchedule>
     */
    public function all(): array
    {
        return $this->schedules;
    }

    /**
     * The job of the schedule, over its period at the current moment.
     *
     * @throws NotFound when no schedule has that name
     */
    public function job(string $name): ReportJob
    {
        $schedule = $this->schedules[$name] ?? throw NotFound::of('schedule', $name);

        return new ReportJob(
            $schedule->report,
            $schedule->format,
            new ReportRequest($this->periods->period($schedule->period), $schedule->parameters),
            $schedule->delivery,
            $schedule->recipients,
        );
    }
}
