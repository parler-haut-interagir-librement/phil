<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Application;

use Phil\OrganizationsBundle\Application\Contract\OrganizationStore;
use Phil\OrganizationsBundle\Domain\ValueObject\OrganizationId;

/**
 * The recommendation of at least two owners, so that an organization is never orphaned: the second owner is the one
 * who names a new owner when the first is gone. The bundle never requires it; it only says where it is not met.
 */
final readonly class OwnershipCheck
{
    /**
     * @param positive-int $recommended the owners recommended
     */
    public function __construct(
        private OrganizationStore $store,
        public int $recommended = 2,
    ) {
    }

    public function owners(OrganizationId $organizationId): int
    {
        return $this->store->countWithRole($organizationId, RoleCatalog::OWNER);
    }

    public function meetsRecommendation(OrganizationId $organizationId): bool
    {
        return $this->owners($organizationId) >= $this->recommended;
    }

    /**
     * @param int<1, 1000> $limit
     *
     * @return array<string, int> the organizations with fewer owners than recommended, by identifier, with their
     *                            owners, those without any first
     */
    public function belowRecommendation(int $limit = 100): array
    {
        return $this->store->organizationsWithFewer(RoleCatalog::OWNER, $this->recommended, $limit);
    }
}
