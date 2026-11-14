<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Exception;

use Phil\ReportingBundle\Domain\Exception\ReportingException;
use RuntimeException;

/**
 * An export whose bytes did not all reach their stream: the file could not be created, or the stream could not be
 * flushed and closed, as when the disk is full.
 */
final class ExportFailed extends RuntimeException implements ReportingException
{
    public static function because(string $reason): self
    {
        return new self(\sprintf('The export failed: %s.', $reason));
    }
}
