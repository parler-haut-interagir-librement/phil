<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Event;

/**
 * The room opened on its server: its first participant joined it.
 */
final readonly class ClassroomOpened
{
    public function __construct(
        public string $room,
    ) {
    }
}
