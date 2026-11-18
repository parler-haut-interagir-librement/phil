<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A team deleted, with its memberships.
 */
final readonly class TeamDeleted
{
    public function __construct(
        public string $organizationId,
        public string $teamId,
        public string $actorId,
    ) {
    }
}
