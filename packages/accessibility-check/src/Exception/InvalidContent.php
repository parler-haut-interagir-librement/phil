<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Exception;

use RuntimeException;

/**
 * A content that cannot be read: HTML that is not UTF-8.
 */
final class InvalidContent extends RuntimeException implements AccessibilityCheckException
{
    public static function notUtf8(): self
    {
        return new self('The content is not valid UTF-8.');
    }
}
