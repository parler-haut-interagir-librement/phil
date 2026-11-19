<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

/**
 * Who is in a room at the moment the server was asked: its state and the participants it names by the identifier
 * they joined with. A participant who joined without one, by a link the application did not write, is not listed.
 */
final readonly class Presences
{
    /**
     * @param list<ParticipantId> $participants each once
     */
    public function __construct(
        public RoomState $state,
        public array $participants = [],
    ) {
    }

    public static function gone(): self
    {
        return new self(RoomState::Gone);
    }
}
