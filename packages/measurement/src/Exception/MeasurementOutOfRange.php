<?php

declare(strict_types=1);

namespace Phil\Measurement\Exception;

use RangeException;

/**
 * A value or a computation that leaves the exact range of the library: a decimal of more than 18 significant digits,
 * or a conversion whose exact fraction no longer fits in native integers. The library refuses it rather than turn it
 * into a float.
 */
final class MeasurementOutOfRange extends RangeException implements MeasurementException
{
    public static function tooManyDigits(string $value): self
    {
        return new self(\sprintf('"%s" has more than 18 significant digits: an exact measurement holds 18 at most.', mb_strcut($value, 0, 64)));
    }

    public static function intermediate(): self
    {
        return new self('The exact result leaves the range of native integers; round the measurement first with round().');
    }
}
