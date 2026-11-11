<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Exception;

use Phil\ReportingBundle\Domain\Exception\ReportingException;
use RuntimeException;

/**
 * A key that names nothing: no source, report, format, delivery, dashboard, schedule or period of that name.
 */
final class NotFound extends RuntimeException implements ReportingException
{
    public static function of(string $kind, string $key): self
    {
        return new self(\sprintf('There is no %s "%s".', $kind, $key));
    }
}
