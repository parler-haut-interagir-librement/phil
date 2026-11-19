<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Event;

/**
 * A participant entered the room: a stay of theirs began. Identifiers only: the participant is the stable identifier of their account.
 */
final readonly class ParticipantArrived
{
    public function __construct(
        public string $room,
        public string $participant,
    ) {
    }
}
