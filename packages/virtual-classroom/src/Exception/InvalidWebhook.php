<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Exception;

use RuntimeException;

/**
 * A notification the library refuses: its signature does not match, it is too old, or its body is not what the provider sends.
 */
final class InvalidWebhook extends RuntimeException implements VirtualClassroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
