<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Application\Contract;

use Phil\Url\Url;
use Phil\VirtualClassroom\RoomId;

/**
 * The absolute addresses a server calls back for a room: the end of the room, and its signed events; null when the
 * application cannot be reached from outside, in development for instance.
 */
interface CallbackUrls
{
    public function ended(string $provider, RoomId $room): ?Url;

    public function events(string $provider, RoomId $room): ?Url;
}
