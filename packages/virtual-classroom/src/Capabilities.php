<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom;

/**
 * What a provider can do beyond opening a room and writing join links: end a room, say who is in it now, list its
 * recordings, and receive notifications. A call to what it cannot do throws `Unsupported`.
 */
final readonly class Capabilities
{
    public function __construct(
        public bool $end,
        public bool $presences,
        public bool $recordings,
        public bool $webhooks,
    ) {
    }
}
