<?php

declare(strict_types=1);

namespace Phil\Geo\Exception;

use InvalidArgumentException;

/**
 * A latitude, a longitude, a distance or a text that is not one.
 */
final class InvalidCoordinates extends InvalidArgumentException implements GeoException
{
    public static function latitude(float $latitude): self
    {
        return new self(\sprintf('A latitude is -90 to 90 degrees; %s is not.', self::number($latitude)));
    }

    public static function longitude(float $longitude): self
    {
        return new self(\sprintf('A longitude is -180 to 180 degrees; %s is not.', self::number($longitude)));
    }

    public static function text(string $text): self
    {
        return new self(\sprintf('"%s" is not a "latitude,longitude" pair in decimal degrees.', $text));
    }

    public static function distance(float $meters): self
    {
        return new self(\sprintf('A distance is zero meters or more, and finite; %s is not.', self::number($meters)));
    }

    public static function precision(int $decimals): self
    {
        return new self(\sprintf('Coordinates keep 0 to 7 decimals; %d is refused.', $decimals));
    }

    /**
     * A float as a person reads it, `NAN` and `INF` included.
     */
    private static function number(float $value): string
    {
        if (is_nan($value)) {
            return 'NAN';
        }

        return is_infinite($value) ? (\INF === $value ? 'INF' : '-INF') : (string) $value;
    }
}
