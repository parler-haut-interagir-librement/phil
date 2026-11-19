<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

/**
 * A room the server knows: its identifier, whether it records, and the key of its session when the server gives one —
 * the creation time of BigBlueButton, which a join link must repeat, so that a link of an earlier session of the
 * same room is refused.
 */
final readonly class OpenedRoom
{
    public function __construct(
        public RoomId $id,
        public bool $record = false,
        public ?string $sessionKey = null,
    ) {
    }
}
