<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Exception;

use InvalidArgumentException;

/**
 * No meter of that name was declared.
 */
final class UnknownMeter extends InvalidArgumentException implements UsageMeterException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No meter is named "%s".', $name));
    }
}
