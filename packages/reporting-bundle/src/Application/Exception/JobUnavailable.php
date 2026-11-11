<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Exception;

use Phil\ReportingBundle\Domain\Exception\ReportingException;
use RuntimeException;

/**
 * A job or an export the store cannot give back: none under the identifier, or one it cannot read.
 */
final class JobUnavailable extends RuntimeException implements ReportingException
{
    public static function of(string $id, string $reason): self
    {
        return new self(\sprintf('The report job or export "%s" is unavailable: %s.', $id, $reason));
    }
}
