<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Exception;

use RuntimeException;

/**
 * The conferencing server did not answer in time, answered with a server error, or could not be reached: a call to retry later.
 */
final class ProviderUnavailable extends RuntimeException implements VirtualClassroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
