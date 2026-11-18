<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * The roles of a member changed, from the previous ones to the new ones.
 */
final readonly class MemberRolesChanged
{
    public function __construct(
        public string $organizationId,
        public string $memberId,
        /** @var list<string> */
        public array $previousRoles,
        /** @var list<string> */
        public array $roles,
        public string $actorId,
    ) {
    }
}
