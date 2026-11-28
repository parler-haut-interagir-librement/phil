<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Event;

use DateTimeImmutable;

/**
 * A member who no longer belongs to a dynamic segment: the rule fails for them, they were excluded, or they left the
 * audience. It carries identifiers only.
 */
final readonly class MemberLeft
{
    public function __construct(
        public string $segment,
        public string $member,
        public DateTimeImmutable $at,
    ) {
    }
}
