<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Application\Exception;

use RuntimeException;
use Throwable;

/**
 * A batch of facts did not reach its destination: the feed keeps its place and sends the batch again next time.
 */
final class ExportFailed extends RuntimeException
{
    public static function because(string $reason, ?Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
