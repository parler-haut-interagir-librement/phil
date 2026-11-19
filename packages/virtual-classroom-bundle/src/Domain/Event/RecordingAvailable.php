<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Event;

/**
 * A recording of the room is known: the server announced it, or listed it when the room was synced.
 */
final readonly class RecordingAvailable
{
    public function __construct(
        public string $room,
        public string $recording,
    ) {
    }
}
