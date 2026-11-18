<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A member no longer in a team, with the roles they held there; the actor is null when the member left the organization
 * or was erased.
 */
final readonly class TeamMemberRemoved
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
