<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Event;

use DateTimeImmutable;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\RoomId;

/**
 * A participant left the room: they closed it, lost their connection or were removed.
 */
final readonly class ParticipantLeft implements RoomEvent
{
    public function __construct(
        public RoomId $room,
        public ParticipantId $participant,
        public DateTimeImmutable $at,
    ) {
    }
}
