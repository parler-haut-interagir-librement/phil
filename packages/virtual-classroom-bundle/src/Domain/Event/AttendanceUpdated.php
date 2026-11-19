<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Event;

/**
 * The attendance of an ended room changed: the room ended, or a stay was reported after its end. A listener reads the attendance from `Classrooms::attendance()`.
 */
final readonly class AttendanceUpdated
{
    public function __construct(
        public string $room,
    ) {
    }
}
