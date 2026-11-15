<?php

declare(strict_types=1);

namespace Phil\ModerationBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * What a member, a target, a report, a decision or a note cannot be made of.
 */
final class InvalidModeration extends InvalidArgumentException implements ModerationException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
