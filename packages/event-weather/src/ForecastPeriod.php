<?php

declare(strict_types=1);

namespace Phil\EventWeather;

use DateTimeImmutable;
use DateTimeZone;
use Phil\EventWeather\Exception\InvalidArgument;

/**
 * The weather of one stretch of time, from `from` to `to`, in UTC: the condition, the temperatures in degrees Celsius,
 * the chance of precipitation in percent, the precipitation in millimetres, the wind in kilometres per hour and the
 * direction it blows from in degrees, and the provider's own words. What a provider does not give is null.
 */
final readonly class ForecastPeriod
{
    public DateTimeImmutable $from;

    public DateTimeImmutable $to;

    /**
     * @throws InvalidArgument when the period ends before it starts, or a percentage or a direction is out of range
     */
    public function __construct(
        DateTimeImmutable $from,
        DateTimeImmutable $to,
        public Condition $condition,
        public ?float $temperature = null,
        public ?int $precipitationProbability = null,
        public ?float $precipitation = null,
        public ?float $windSpeed = null,
        public ?float $windGust = null,
        public ?int $windDirection = null,
        public ?float $minTemperature = null,
        public ?float $maxTemperature = null,
        public ?string $summary = null,
    ) {
        $utc = new DateTimeZone('UTC');
        $this->from = $from->setTimezone($utc);
        $this->to = $to->setTimezone($utc);

        if ($this->to < $this->from) {
            throw new InvalidArgument('A forecast period ends after it starts.');
        }

        if (null !== $precipitationProbability && ($precipitationProbability < 0 || $precipitationProbability > 100)) {
            throw new InvalidArgument('A chance of precipitation is between 0 and 100 percent.');
        }

        if (null !== $windDirection && ($windDirection < 0 || $windDirection >= 360)) {
            throw new InvalidArgument('A wind direction is between 0 and 359 degrees.');
        }
    }

    /**
     * Whether some instant of the period falls in `[$start, $end)`.
     */
    public function overlaps(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        return $this->from < $end && $this->to > $start;
    }
}
