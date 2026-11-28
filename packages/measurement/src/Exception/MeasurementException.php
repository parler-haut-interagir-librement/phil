<?php

declare(strict_types=1);

namespace Phil\Measurement\Exception;

use Throwable;

/**
 * Every exception the library throws, so that an application maps them in one place: each one is a mistake of the
 * caller, of its input or of its unit definitions, never a failure of the library.
 */
interface MeasurementException extends Throwable
{
}
