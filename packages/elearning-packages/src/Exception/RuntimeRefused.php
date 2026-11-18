<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Exception;

use InvalidArgumentException;

/**
 * Thrown when the data of a SCORM run time cannot be used: a stored state that does not decode, a format that is not
 * SCORM, or a launch value out of its bounds.
 */
final class RuntimeRefused extends InvalidArgumentException implements ElearningPackagesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
