<?php

declare(strict_types=1);

namespace Phil\EventWeather\Exception;

use InvalidArgumentException;

/**
 * A value the application gave that the library cannot use: a contact, a country, an event that ends before it
 * starts.
 */
final class InvalidArgument extends InvalidArgumentException implements EventWeatherException
{
}
