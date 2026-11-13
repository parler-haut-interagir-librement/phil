<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Exception;

use RuntimeException;

/**
 * A voter cast a ballot in a poll where one of theirs is already kept, or recorded as cast for an anonymous poll.
 */
final class AlreadyVoted extends RuntimeException implements PollBundleException
{
    public static function in(string $poll): self
    {
        return new self(\sprintf('A ballot was already cast by this voter in the poll "%s".', $poll));
    }
}
