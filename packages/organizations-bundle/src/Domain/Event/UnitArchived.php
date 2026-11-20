<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A unit archived, with the units below it that were not archived yet: `unitIds` names them all, the unit first.
 */
final readonly class UnitArchived
{
    public function __construct(
        public string $organizationId,
        public string $unitId,
        /** @var non-empty-list<string> */
        public array $unitIds,
        public string $actorId,
    ) {
    }
}
