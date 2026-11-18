<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A team created inside an organization.
 */
final readonly class TeamCreated
{
    public function __construct(
        public string $organizationId,
        public string $teamId,
        public string $actorId,
    ) {
    }
}
