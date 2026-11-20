<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * The roles of a member in a unit changed.
 */
final readonly class UnitMemberRolesChanged
{
    public function __construct(
        public string $organizationId,
        public string $unitId,
        public string $memberId,
        /** @var list<string> */
        public array $previousRoles,
        /** @var list<string> */
        public array $roles,
        public string $actorId,
    ) {
    }
}
