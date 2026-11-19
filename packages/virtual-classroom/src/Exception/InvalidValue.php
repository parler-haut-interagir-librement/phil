<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Exception;

use InvalidArgumentException;

/**
 * A value a room, a participant or a provider setting cannot hold: an identifier out of its pattern, a title too long, a secret too short.
 */
final class InvalidValue extends InvalidArgumentException implements VirtualClassroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
