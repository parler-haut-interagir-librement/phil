<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Exception;

use InvalidArgumentException;
use Phil\ReportingBundle\Domain\Exception\ReportingException;

/**
 * A report job or request that cannot be kept: a key, a parameter or a recipient out of shape, more parameters or
 * recipients than the limits allow, or a row without one cell per column.
 */
final class InvalidReportJob extends InvalidArgumentException implements ReportingException
{
    public static function because(string $problem): self
    {
        return new self(\sprintf('The report job is invalid: %s.', $problem));
    }
}
