<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Exception;

use LogicException;

/**
 * What a provider cannot do: a Jitsi server has no call to end a room, nor to list who is in it.
 */
final class Unsupported extends LogicException implements VirtualClassroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
