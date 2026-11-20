<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A unit restored, with the units below it that were archived with it: `unitIds` names them all, the unit first.
 */
final readonly class UnitRestored
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
