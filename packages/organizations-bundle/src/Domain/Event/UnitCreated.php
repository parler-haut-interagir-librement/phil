<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A unit created in an organization, a root when its parent is null.
 */
final readonly class UnitCreated
{
    public function __construct(
        public string $organizationId,
        public string $unitId,
        public ?string $parentId,
        public string $actorId,
    ) {
    }
}
