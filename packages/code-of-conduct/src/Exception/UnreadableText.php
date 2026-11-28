<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Exception;

use RuntimeException;
use Throwable;

/**
 * A sealed text of a report that does not open: altered, moved to another report, or sealed under a key the KMS does
 * not hold.
 */
final class UnreadableText extends RuntimeException implements CodeOfConductException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
