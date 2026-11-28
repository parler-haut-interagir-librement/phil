<?php

declare(strict_types=1);

namespace Phil\Measurement\Exception;

use InvalidArgumentException;

/**
 * A unit or family definition the registry refuses: a missing standard unit, a factor that is not a positive exact
 * number, a code used twice, or more definitions than the registry holds.
 */
final class InvalidUnitDefinition extends InvalidArgumentException implements MeasurementException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
