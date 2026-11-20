<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A unit moved, with the units below it, from one parent to another; a null parent is the root.
 */
final readonly class UnitMoved
{
    public function __construct(
        public string $organizationId,
        public string $unitId,
        public ?string $previousParentId,
        public ?string $parentId,
        public string $actorId,
    ) {
    }
}
