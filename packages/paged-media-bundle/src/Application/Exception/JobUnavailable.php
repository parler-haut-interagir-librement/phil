<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application\Exception;

use Phil\PagedMedia\Exception\PagedMediaException;
use RuntimeException;

/**
 * A job a message names that the job store cannot give: none is kept under its identifier, it cannot be read back, or
 * it is not the kind of job the message handles.
 */
final class JobUnavailable extends RuntimeException implements PagedMediaException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
