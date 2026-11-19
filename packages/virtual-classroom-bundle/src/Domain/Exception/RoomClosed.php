<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Exception;

/**
 * The room has ended: nobody joins it again.
 */
final class RoomClosed extends ClassroomException
{
    public static function of(string $room): self
    {
        return new self(\sprintf('The room "%s" has ended.', $room));
    }
}
