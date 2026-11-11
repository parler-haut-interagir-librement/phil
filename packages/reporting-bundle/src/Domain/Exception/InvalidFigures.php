<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * Figures that cannot be shown: a period that ends before it starts, a value that is not a finite number, a series
 * without one value per label, or more labels, series, buckets or characters than the limits allow.
 */
final class InvalidFigures extends InvalidArgumentException implements ReportingException
{
    public static function because(string $problem): self
    {
        return new self(\sprintf('The figures are invalid: %s.', $problem));
    }
}
