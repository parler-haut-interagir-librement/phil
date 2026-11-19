<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Contract;

use DateTimeImmutable;
use Phil\VirtualClassroom\RoomId;

/**
 * What a notification of the server says happened to a room, and when, in UTC.
 */
interface RoomEvent
{
    public RoomId $room { get; }

    public DateTimeImmutable $at { get; }
}
