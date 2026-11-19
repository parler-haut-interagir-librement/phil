<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Event;

/**
 * A participant left the room: their stay ended. Identifiers only: the participant is the stable identifier of their account.
 */
final readonly class ParticipantDeparted
{
    public function __construct(
        public string $room,
        public string $participant,
    ) {
    }
}
