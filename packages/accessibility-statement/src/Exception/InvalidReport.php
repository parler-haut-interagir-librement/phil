<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement\Exception;

use InvalidArgumentException;

/**
 * The JSON of an accessibility report that cannot be read back: not JSON, too large, too deep, or not shaped as the
 * report of phil/accessibility-check.
 */
final class InvalidReport extends InvalidArgumentException implements AccessibilityStatementException
{
    public static function because(string $message): self
    {
        return new self($message);
    }
}
