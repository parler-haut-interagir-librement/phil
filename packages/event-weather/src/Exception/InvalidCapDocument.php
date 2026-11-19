<?php

declare(strict_types=1);

namespace Phil\EventWeather\Exception;

use UnexpectedValueException;

/**
 * A document the CAP reader refuses: not XML, not a CAP 1.2 alert, larger than its limit, or without a field the
 * standard requires.
 */
final class InvalidCapDocument extends UnexpectedValueException implements EventWeatherException
{
}
