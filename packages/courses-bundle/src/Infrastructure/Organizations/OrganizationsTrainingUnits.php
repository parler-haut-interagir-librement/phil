<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Organizations;

use Override;
use Phil\CoursesBundle\Application\Contract\TrainingUnits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;

/**
 * The unit the training of a learner counts in, from the tree of units of phil/organizations-bundle: the unit they
 * belong to, today. A learner of several units counts in the first by identifier, in the first organization, by
 * identifier, where they belong to any; one that is no member identifier, or belongs to no unit, counts in none.
 */
final readonly class OrganizationsTrainingUnits implements TrainingUnits
{
    public function __construct(
        private Organizations $organizations,
        private UnitTree $tree,
    ) {
    }

    #[Override]
    public function unitOf(LearnerId $learner): ?string
    {
        try {
            $member = MemberId::fromString($learner->value);
        } catch (InvalidOrganization) {
            return null;
        }

        foreach ($this->organizations->ofMember($member) as $membership) {
            foreach ($this->tree->unitIdsOf($membership->organizationId, $member) as $unit) {
                return $unit->value;
            }
        }

        return null;
    }
}
