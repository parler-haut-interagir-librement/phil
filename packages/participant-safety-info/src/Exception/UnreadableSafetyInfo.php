<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Exception;

use RuntimeException;
use Throwable;

/**
 * Sealed safety information that does not open or does not decode: altered, moved to another row, sealed under a key
 * the KMS does not hold, or written by something other than the package.
 */
final class UnreadableSafetyInfo extends RuntimeException implements ParticipantSafetyInfoException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
