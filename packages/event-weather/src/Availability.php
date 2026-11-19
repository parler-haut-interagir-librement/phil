<?php

declare(strict_types=1);

namespace Phil\EventWeather;

/**
 * Whether there is a forecast to show for an event, and why not when there is none.
 */
enum Availability: string
{
    /**
     * A provider forecast the event's time.
     */
    case Available = 'available';

    /**
     * The event starts beyond the horizon of every provider of its place: a forecast that far ahead would mislead.
     */
    case TooFar = 'too-far';

    /**
     * The event has ended.
     */
    case Past = 'past';

    /**
     * No provider forecasts for the event's place.
     */
    case Unsupported = 'unsupported';

    /**
     * The providers did not answer, or answered with what could not be read; the page shows no forecast this time.
     */
    case Unavailable = 'unavailable';
}
