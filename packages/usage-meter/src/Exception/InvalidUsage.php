<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Exception;

use InvalidArgumentException;

/**
 * A meter, a limit, a period or a call was given a value it cannot work with.
 */
final class InvalidUsage extends InvalidArgumentException implements UsageMeterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
