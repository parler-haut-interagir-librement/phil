<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Exception;

use InvalidArgumentException;

/**
 * Thrown when the sequencing engine is handed what it cannot use: a stored state that does not match the activity
 * tree or holds a value out of its bounds, or results reported while no activity is delivered.
 */
final class SequencingRefused extends InvalidArgumentException implements ElearningPackagesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
