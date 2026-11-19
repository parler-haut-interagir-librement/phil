<?php

declare(strict_types=1);

namespace Phil\EventWeather;

use DateTimeImmutable;

/**
 * What a page shows of the weather of one event: whether there is a forecast, the periods of the event's time, the
 * alerts in effect during it, and the attributions of every provider whose data it holds. A value the application
 * renders as it likes; the library writes no markup.
 */
final readonly class EventForecast
{
    /**
     * @param list<ForecastPeriod> $periods
     * @param list<WeatherAlert>   $alerts
     * @param list<Attribution>    $attributions
     */
    public function __construct(
        public Availability $availability,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public ?Forecast $forecast = null,
        public array $periods = [],
        public array $alerts = [],
        public array $attributions = [],
    ) {
    }

    public function isAvailable(): bool
    {
        return Availability::Available === $this->availability;
    }

    /**
     * The most severe alert in effect during the event; null without any.
     */
    public function mostSevereAlert(): ?WeatherAlert
    {
        $worst = null;

        foreach ($this->alerts as $alert) {
            if (null === $worst || !$worst->severity->isAtLeast($alert->severity)) {
                $worst = $alert;
            }
        }

        return $worst;
    }

    /**
     * The highest chance of precipitation of the event's periods; null when none gives one.
     */
    public function maxPrecipitationProbability(): ?int
    {
        $chances = array_filter(array_map(static fn (ForecastPeriod $period): ?int => $period->precipitationProbability, $this->periods), static fn (?int $chance): bool => null !== $chance);

        return [] === $chances ? null : max($chances);
    }
}
