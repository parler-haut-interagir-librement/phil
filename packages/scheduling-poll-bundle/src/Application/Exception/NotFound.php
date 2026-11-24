<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Exception;

use Phil\SchedulingPollBundle\Domain\Exception\SchedulingPollException;
use RuntimeException;

/**
 * No scheduling poll, or no respondent of it, has this identifier.
 */
final class NotFound extends RuntimeException implements SchedulingPollException
{
    public static function poll(string $id): self
    {
        return new self(\sprintf('No scheduling poll is identified by "%s".', $id));
    }

    public static function respondent(string $id): self
    {
        return new self(\sprintf('No respondent is identified by "%s".', $id));
    }
}
