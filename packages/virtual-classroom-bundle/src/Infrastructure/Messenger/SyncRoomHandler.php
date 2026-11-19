<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Messenger;

use Phil\VirtualClassroom\Exception\InvalidValue;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Domain\Exception\UnknownRoom;

/**
 * Syncs the room a `SyncRoom` names. A message of a room that does not exist, or no longer does, is dropped; a server
 * out of reach throws, and the transport retries.
 */
final readonly class SyncRoomHandler
{
    public function __construct(
        private Classrooms $classrooms,
    ) {
    }

    public function __invoke(SyncRoom $message): void
    {
        try {
            $this->classrooms->sync(RoomId::fromString($message->room));
        } catch (InvalidValue|UnknownRoom) {
            // A room that is no room or that is gone has nothing to sync.
        }
    }
}
