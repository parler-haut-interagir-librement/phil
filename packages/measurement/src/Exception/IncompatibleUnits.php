<?php

declare(strict_types=1);

namespace Phil\Measurement\Exception;

use LogicException;

/**
 * An operation between measurements that has no meaning: a mass converted to a length, two temperatures added, or a
 * fuel consumption summed.
 */
final class IncompatibleUnits extends LogicException implements MeasurementException
{
    public static function families(string $from, string $to): self
    {
        return new self(\sprintf('A measurement of the family "%s" cannot be turned into one of the family "%s".', $from, $to));
    }

    public static function notAdditive(string $family): self
    {
        return new self(\sprintf('Measurements of the family "%s" are not added together: a temperature takes a temperature difference, and a fuel consumption is never summed.', $family));
    }

    public static function noDifference(string $family): self
    {
        return new self(\sprintf('The family "%s" has no family of differences to subtract into.', $family));
    }
}
