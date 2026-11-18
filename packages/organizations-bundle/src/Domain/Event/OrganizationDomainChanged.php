<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * The verified email domain of an organization recorded, changed or removed.
 */
final readonly class OrganizationDomainChanged
{
    public function __construct(
        public string $organizationId,
        public string $actorId,
    ) {
    }
}
