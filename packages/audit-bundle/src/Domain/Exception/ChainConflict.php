<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Exception;

use Phil\DddBundle\Domain\Exception\DomainError;
use RuntimeException;

/**
 * A row appended at a place another process took first: the caller reads the head again and retries.
 */
final class ChainConflict extends RuntimeException implements DomainError
{
    public static function at(string $segment, int $sequence): self
    {
        return new self(\sprintf('Row %d of "%s" was appended by another process.', $sequence, $segment));
    }
}
