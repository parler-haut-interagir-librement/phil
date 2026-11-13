<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A message of the frame protocol that is not one: not an object, of another protocol or version, of an unknown type, or with a value out of bounds.
 */
final class InvalidEmbedMessage extends InvalidArgumentException implements EmbedException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
