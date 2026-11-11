<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Exception;

use Phil\ReportingBundle\Domain\Exception\ReportingException;
use RuntimeException;

/**
 * A report with more rows than the configuration allows, stopped at the first row beyond the limit.
 */
final class ReportTooLarge extends RuntimeException implements ReportingException
{
    public static function of(string $report, int $limit): self
    {
        return new self(\sprintf('The report "%s" has more than %d rows.', $report, $limit));
    }
}
