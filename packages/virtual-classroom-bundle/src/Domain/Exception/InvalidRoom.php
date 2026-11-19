<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Exception;

/**
 * A value a room cannot hold: a title, a duration, an owner.
 */
final class InvalidRoom extends ClassroomException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
