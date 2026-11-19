<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Exception;

/**
 * No room of the application has this identifier.
 */
final class UnknownRoom extends ClassroomException
{
    public static function of(string $room): self
    {
        return new self(\sprintf('No room is named "%s".', $room));
    }
}
