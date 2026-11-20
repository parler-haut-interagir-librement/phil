<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * A unit renamed.
 */
final readonly class UnitRenamed
{
    public function __construct(
        public string $organizationId,
        public string $unitId,
        public string $actorId,
    ) {
    }
}
