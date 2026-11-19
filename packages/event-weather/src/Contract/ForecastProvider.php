<?php

declare(strict_types=1);

namespace Phil\EventWeather\Contract;

use Phil\EventWeather\Attribution;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Phil\EventWeather\Forecast;

/**
 * A weather service that forecasts for some places. An adapter is named after its service, keeps no state between
 * calls, and sends its requests through the HTTP client it is given, which the application wraps in a cache.
 */
interface ForecastProvider
{
    public function attribution(): Attribution;

    /**
     * Whether the service forecasts for this place.
     */
    public function supports(EventLocation $location): bool;

    /**
     * How many hours ahead of now its forecast reaches.
     *
     * @return positive-int
     */
    public function horizonHours(): int;

    /**
     * @throws WeatherUnavailable when the service does not answer, answers with an error, or sends what its format does
     *                            not describe
     */
    public function forecast(EventLocation $location): Forecast;
}
