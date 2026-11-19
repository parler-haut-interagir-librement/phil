<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Event;

use DateTimeImmutable;

/**
 * A member took back a reaction, or changed it for another. Identifiers only, so the event can also travel as a message.
 */
final readonly class ReactionRemoved
{
    public function __construct(
        public string $content,
        public string $member,
        public string $code,
        public DateTimeImmutable $at,
    ) {
    }
}
