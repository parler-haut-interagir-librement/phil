<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Tests\Fixtures;

use DateTimeImmutable;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\RoomId;

/**
 * A notification of a kind the service does not know, as a server of the application could send.
 */
final readonly class OtherEvent implements RoomEvent
{
    public function __construct(
        public RoomId $room,
        public DateTimeImmutable $at,
    ) {
    }
}
