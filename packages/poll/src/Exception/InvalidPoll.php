<?php

declare(strict_types=1);

namespace Phil\Poll\Exception;

use InvalidArgumentException;

/**
 * The rules of a poll were given options or bounds they cannot work with, or a counting method was asked to count a
 * kind of ballot it does not count.
 */
final class InvalidPoll extends InvalidArgumentException implements PollException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
