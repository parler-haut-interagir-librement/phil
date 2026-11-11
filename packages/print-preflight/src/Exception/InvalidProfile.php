<?php

declare(strict_types=1);

namespace Phil\PrintPreflight\Exception;

use InvalidArgumentException;

/**
 * A printer profile whose rules contradict each other or cannot hold: a target resolution below the minimum, a maximum
 * page count below the minimum, a page multiple below one.
 */
final class InvalidProfile extends InvalidArgumentException implements PrintPreflightException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
