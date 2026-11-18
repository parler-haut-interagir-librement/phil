<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * An archived organization in use again.
 */
final readonly class OrganizationRestored
{
    public function __construct(
        public string $organizationId,
        public string $actorId,
    ) {
    }
}
