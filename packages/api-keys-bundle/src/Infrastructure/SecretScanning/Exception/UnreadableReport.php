<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Infrastructure\SecretScanning\Exception;

use InvalidArgumentException;
use Phil\ApiKeysBundle\Domain\Exception\ApiKeysException;
use Throwable;

/**
 * What a secret scanner sent, or what it publishes of its keys, cannot be read: not JSON, not the shape expected, or
 * past a bound.
 */
final class UnreadableReport extends InvalidArgumentException implements ApiKeysException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
