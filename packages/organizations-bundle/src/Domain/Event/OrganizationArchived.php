<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * An organization archived: read only until it is restored.
 */
final readonly class OrganizationArchived
{
    public function __construct(
        public string $organizationId,
        public string $actorId,
    ) {
    }
}
