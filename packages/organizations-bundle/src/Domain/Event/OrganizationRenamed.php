<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * An organization renamed, its slug changed with it or not.
 */
final readonly class OrganizationRenamed
{
    public function __construct(
        public string $organizationId,
        public string $actorId,
    ) {
    }
}
