<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Domain\Event;

/**
 * The room ended.
 */
final readonly class ClassroomEnded
{
    public function __construct(
        public string $room,
    ) {
    }
}
