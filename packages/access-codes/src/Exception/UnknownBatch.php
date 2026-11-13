<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Exception;

use RuntimeException;

/**
 * A batch the store does not hold.
 */
final class UnknownBatch extends RuntimeException implements AccessCodesException
{
    public static function withId(string $batchId): self
    {
        return new self(\sprintf('No batch of codes has the identifier "%s".', $batchId));
    }
}
