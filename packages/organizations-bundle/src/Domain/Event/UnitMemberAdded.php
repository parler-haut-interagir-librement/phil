<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A member added to a unit, with the roles they hold there and below.
 */
final readonly class UnitMemberAdded
{
    public function __construct(
        public string $organizationId,
        public string $unitId,
        public string $memberId,
        /** @var list<string> */
        public array $roles,
        public string $actorId,
    ) {
    }
}
