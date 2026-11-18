<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A member of the organization added to a team with roles of the team.
 */
final readonly class TeamMemberAdded
{
    public function __construct(
        public string $organizationId,
        public string $teamId,
        public string $memberId,
        /** @var list<string> */
        public array $roles,
        public ?string $actorId = null,
    ) {
    }
}
