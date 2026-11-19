<?php

declare(strict_types=1);

namespace Phil\AiBundle\Domain\Exception;

use InvalidArgumentException;

/**
 * A task kind, a subject, a token count, a price or a cost out of shape.
 */
final class InvalidAiValue extends InvalidArgumentException implements AiException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
