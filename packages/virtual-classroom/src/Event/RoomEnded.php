<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Event;

use DateTimeImmutable;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\RoomId;

/**
 * The room ended: a moderator ended it, its last participant left, or its time ran out.
 */
final readonly class RoomEnded implements RoomEvent
{
    public function __construct(
        public RoomId $room,
        public DateTimeImmutable $at,
    ) {
    }
}
