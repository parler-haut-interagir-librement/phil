<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\Event;

use DateTimeImmutable;
use Phil\VirtualClassroom\Contract\RoomEvent;
use Phil\VirtualClassroom\ParticipantId;
use Phil\VirtualClassroom\RoomId;

/**
 * A participant entered the room, named by the identifier of their join link.
 */
final readonly class ParticipantJoined implements RoomEvent
{
    public function __construct(
        public RoomId $room,
        public ParticipantId $participant,
        public DateTimeImmutable $at,
    ) {
    }
}
