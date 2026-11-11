<?php

declare(strict_types=1);

namespace Phil\Poll\Exception;

use RuntimeException;

/**
 * A counting method was asked for by a name none of the methods declared answers to.
 */
final class UnknownCountingMethod extends RuntimeException implements PollException
{
    public static function named(string $name): self
    {
        return new self(\sprintf('No counting method is named "%s".', $name));
    }
}
