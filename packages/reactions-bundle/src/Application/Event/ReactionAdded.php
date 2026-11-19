<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application\Event;

use DateTimeImmutable;

/**
 * A member chose a reaction on a content; changing a reaction is a removal, then an addition. Identifiers only, so the
 * event can also travel as a message.
 */
final readonly class ReactionAdded
{
    public function __construct(
        public string $content,
        public string $member,
        public string $code,
        public DateTimeImmutable $at,
    ) {
    }
}
