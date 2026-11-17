<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Exception;

use InvalidArgumentException;

/**
 * Limits a reader cannot work with: every bound is a positive number.
 */
final class InvalidLimits extends InvalidArgumentException implements ElearningPackagesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
