<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Application\Event;

use DateTimeImmutable;

/**
 * A member came into an access group through an entry point, until `$expiresAt` when the group's membership ends on
 * its own; identifiers only.
 */
final readonly class MemberJoinedGroup
{
    public function __construct(
        public string $group,
        public string $member,
        public string $entryPoint,
        public ?DateTimeImmutable $expiresAt,
    ) {
    }
}
