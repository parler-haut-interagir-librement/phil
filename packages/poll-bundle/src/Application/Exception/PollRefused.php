<?php

declare(strict_types=1);

namespace Phil\PollBundle\Application\Exception;

use Phil\PollBundle\Domain\Exception\PollBundleException;
use RuntimeException;

/**
 * The policy refuses to let the actor create, manage, or see the results of a poll.
 */
final class PollRefused extends RuntimeException implements PollBundleException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
