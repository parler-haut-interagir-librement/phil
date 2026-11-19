<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Event;

use DateTimeImmutable;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\Recording;
use Phil\VirtualClassroom\RoomId;

/**
 * A recording of the room is ready: the server names it, and describes it when the notification carries its address;
 * otherwise `VirtualClassroom::recordings()` lists it.
 */
final readonly class RecordingReady implements RoomEvent
{
    public function __construct(
        public RoomId $room,
        public string $recordingId,
        public DateTimeImmutable $at,
        public ?Recording $recording = null,
    ) {
    }
}
