<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A member no longer in a unit, with the roles they held there; the actor is null when the member left the unit or the
 * organization, or was erased.
 */
final readonly class UnitMemberRemoved
{
    public function __construct(
        public string $organizationId,
        public string $unitId,
        public string $memberId,
        /** @var list<string> */
        public array $roles,
        public ?string $actorId = null,
    ) {
    }
}
