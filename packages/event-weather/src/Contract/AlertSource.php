<?php

declare(strict_types=1);

namespace Phil\EventWeather\Contract;

use Phil\EventWeather\Attribution;
use Phil\EventWeather\EventLocation;
use Phil\EventWeather\Exception\WeatherUnavailable;
use Phil\EventWeather\WeatherAlert;

/**
 * A weather service that issues watches, warnings and advisories for some places. An adapter keeps no state between
 * calls.
 */
interface AlertSource
{
    public function attribution(): Attribution;

    public function supports(EventLocation $location): bool;

    /**
     * The alerts the service has in effect or announced for the place, ended ones left out.
     *
     * @return list<WeatherAlert>
     *
     * @throws WeatherUnavailable when the service does not answer, answers with an error, or sends what its format does
     *                            not describe
     */
    public function alerts(EventLocation $location): array;
}
