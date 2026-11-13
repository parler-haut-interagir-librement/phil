<?php

declare(strict_types=1);

namespace Phil\Automations\Exception;

use InvalidArgumentException;

/**
 * A payload or a result that does not hold identifiers only: a key that is not a name, a value that is not a short
 * scalar, or too many entries.
 */
final class InvalidPayload extends InvalidArgumentException implements AutomationsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
