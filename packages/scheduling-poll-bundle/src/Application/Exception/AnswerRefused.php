<?php

declare(strict_types=1);

namespace Phil\SchedulingPollBundle\Application\Exception;

use Phil\SchedulingPollBundle\Domain\Exception\SchedulingPollException;
use RuntimeException;

/**
 * An answer, or a new respondent, was refused: the link opens nothing — malformed, unknown, revoked or expired —, the
 * poll is closed, or it has all the respondents it takes. The message never says which link.
 */
final class AnswerRefused extends RuntimeException implements SchedulingPollException
{
    public static function linkNotUsable(): self
    {
        return new self('This link opens no scheduling poll.');
    }

    public static function closed(): self
    {
        return new self('This scheduling poll is closed: its answers no longer change.');
    }

    public static function full(int $max): self
    {
        return new self(\sprintf('This scheduling poll takes %d respondents at the most.', $max));
    }
}
