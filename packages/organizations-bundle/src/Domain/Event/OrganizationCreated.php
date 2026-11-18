<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Domain\Event;

/**
 * An organization created, and its first owner. Dispatched once the change is committed, like every event of the
 * bundle, with identifiers only: a listener reads the rest from the store.
 */
final readonly class OrganizationCreated
{
    public function __construct(
        public string $organizationId,
        public string $ownerId,
    ) {
    }
}
