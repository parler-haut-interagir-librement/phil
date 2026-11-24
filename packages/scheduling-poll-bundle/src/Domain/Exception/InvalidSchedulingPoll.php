<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A scheduling poll, a proposed date or a respondent is out of bounds: the message says which bound.
 */
final class InvalidSchedulingPoll extends InvalidArgumentException implements SchedulingPollException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
