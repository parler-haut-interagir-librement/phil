<?php

declare(strict_types=1);

namespace Phil\EventWeather;

use DateTimeImmutable;
use DateTimeZone;

/**
 * What one provider forecasts for a place: when it issued it, in UTC, and its periods in order, with the attribution
 * its licence requires.
 */
final readonly class Forecast
{
    public DateTimeImmutable $issuedAt;

    /**
     * @param list<ForecastPeriod> $periods
     */
    public function __construct(
        public Attribution $attribution,
        DateTimeImmutable $issuedAt,
        public array $periods,
    ) {
        $this->issuedAt = $issuedAt->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The periods that overlap `[$start, $end)`.
     *
     * @return list<ForecastPeriod>
     */
    public function between(DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        return array_values(array_filter($this->periods, static fn (ForecastPeriod $period): bool => $period->overlaps($start, $end)));
    }
}
