<?php

declare(strict_types=1);

namespace Phil\Automations\Exception;

use InvalidArgumentException;

/**
 * An automation definition, or one of its parts, that the library refuses: malformed, over a limit, or naming what
 * the catalogue does not hold.
 */
final class InvalidDefinition extends InvalidArgumentException implements AutomationsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
