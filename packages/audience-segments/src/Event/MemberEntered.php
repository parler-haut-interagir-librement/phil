<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Event;

use DateTimeImmutable;

/**
 * A member who now belongs to a dynamic segment, found by a recalculation. It carries identifiers only, so that an
 * application may forward it as a message without an address or a name.
 */
final readonly class MemberEntered
{
    public function __construct(
        public string $segment,
        public string $member,
        public DateTimeImmutable $at,
    ) {
    }
}
