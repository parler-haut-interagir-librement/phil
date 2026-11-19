<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Status;

/**
 * Where a room stands: scheduled until its first participant opens it on the server, open while it runs, ended once
 * ended — by a moderator, by the server, or by the application. An ended room lets nobody in again.
 */
enum RoomStatus: string
{
    case Scheduled = 'scheduled';
    case Open = 'open';
    case Ended = 'ended';
}
