<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatementBundle\Application\Exception;

use Phil\AccessibilityStatement\Exception\AccessibilityStatementException;
use RuntimeException;

/**
 * The statement cannot be regenerated: no version is published to start from, or no audit is there to read.
 */
final class NothingToRegenerate extends RuntimeException implements AccessibilityStatementException
{
    public static function noStatement(): self
    {
        return new self('No statement is published yet: write the first version in the configuration.');
    }

    public static function noAudit(): self
    {
        return new self('No audit is there to read: write the reports of phil/accessibility-check to the audit directory.');
    }
}
