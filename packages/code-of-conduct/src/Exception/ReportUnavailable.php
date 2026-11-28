<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Exception;

use RuntimeException;

/**
 * A report that does not exist, or that the person may not see: the message is the same in both cases, so that it
 * tells nothing of the reports, the people they name or the people who handle them.
 */
final class ReportUnavailable extends RuntimeException implements CodeOfConductException
{
    public static function create(): self
    {
        return new self('This report is not available to you.');
    }
}
