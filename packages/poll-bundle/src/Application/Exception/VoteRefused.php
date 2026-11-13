<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Exception;

use Phil\PollBundle\Domain\Exception\PollBundleException;
use RuntimeException;

/**
 * A ballot was not cast, changed or withdrawn: the poll is not open, the policy refuses the voter, or the poll keeps
 * its ballots in another way than the one asked.
 */
final class VoteRefused extends RuntimeException implements PollBundleException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
