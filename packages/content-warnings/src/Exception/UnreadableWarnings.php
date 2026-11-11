<?php

declare(strict_types=1);

namespace Phil\ContentWarnings\Exception;

use UnexpectedValueException;

/**
 * Stored warnings or preferences that cannot be read back.
 */
final class UnreadableWarnings extends UnexpectedValueException implements ContentWarningsException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('The stored content warnings cannot be read: %s.', rtrim($reason, '.')));
    }
}
