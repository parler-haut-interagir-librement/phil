<?php

declare(strict_types=1);

namespace Phil\PollBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A poll was given a question, options, labels, a counting method or dates it cannot be kept with.
 */
final class InvalidPollDefinition extends InvalidArgumentException implements PollBundleException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
