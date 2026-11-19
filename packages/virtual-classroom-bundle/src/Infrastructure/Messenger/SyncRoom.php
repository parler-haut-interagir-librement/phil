<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Messenger;

/**
 * Asks for a room to be synced with its server: its identifier only.
 */
final readonly class SyncRoom
{
    public function __construct(
        public string $room,
    ) {
    }
}
