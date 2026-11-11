<?php

declare(strict_types=1);

namespace Phil\SvgChart\Exception;

use InvalidArgumentException;

/**
 * A chart configuration cannot be drawn: a missing or misshaped value, a kind of chart not drawn, a colour not written
 * `#rrggbb`, or more labels, series or characters than the limits allow. The message names the path of the value.
 */
final class InvalidChart extends InvalidArgumentException implements SvgChartException
{
    public static function at(string $path, string $problem): self
    {
        return new self(\sprintf('The chart value at "%s" is invalid: %s.', $path, $problem));
    }
}
