<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * What a reaction or its declaration cannot be made of.
 */
final class InvalidReaction extends InvalidArgumentException implements ReactionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
