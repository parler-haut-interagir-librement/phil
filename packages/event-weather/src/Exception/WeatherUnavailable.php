<?php

declare(strict_types=1);

namespace Phil\EventWeather\Exception;

use RuntimeException;
use Throwable;

/**
 * A provider that could not give its forecast or its alerts: it did not answer within the limits, answered with an
 * error, or sent what its format does not describe. `EventWeather` turns it into an unavailable forecast; an
 * application that calls a provider itself catches it.
 */
final class WeatherUnavailable extends RuntimeException implements EventWeatherException
{
    public static function transport(string $provider, Throwable $previous): self
    {
        return new self(\sprintf('%s did not answer: %s', $provider, $previous->getMessage()), 0, $previous);
    }

    public static function status(string $provider, int $status): self
    {
        return new self(\sprintf('%s answered with the status %d.', $provider, $status));
    }

    public static function tooLarge(string $provider, int $limit): self
    {
        return new self(\sprintf('%s sent more than %d bytes.', $provider, $limit));
    }

    public static function format(string $provider, string $problem, ?Throwable $previous = null): self
    {
        return new self(\sprintf('%s sent what its format does not describe: %s', $provider, $problem), 0, $previous);
    }

    public static function noSite(string $provider): self
    {
        return new self(\sprintf('%s has no forecast site near the event.', $provider));
    }
}
