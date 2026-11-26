<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Organizations;

use Override;
use Phil\CoursesBundle\Application\Contract\LearnerUnits;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\OrganizationsBundle\Application\Organizations;
use Phil\OrganizationsBundle\Application\UnitTree;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\ValueObject\MemberId;

/**
 * The units whose catalogues reach a learner, from the tree of units of phil/organizations-bundle: in each
 * organization the learner is a member of, the units they belong to and, unless the catalogues of a unit stay with its
 * own members, the units above them. The learner's identifier names the member; one that is no member identifier
 * belongs to no unit.
 */
final readonly class OrganizationsLearnerUnits implements LearnerUnits
{
    public function __construct(
        private Organizations $organizations,
        private UnitTree $tree,
        private bool $unitsBelow = true,
    ) {
    }

    #[Override]
    public function units(LearnerId $learner): array
    {
        try {
            $member = MemberId::fromString($learner->value);
        } catch (InvalidOrganization) {
            return [];
        }

        $units = [];

        foreach ($this->organizations->ofMember($member) as $membership) {
            foreach ($this->tree->unitIdsOf($membership->organizationId, $member, $this->unitsBelow) as $unit) {
                $units[$unit->value] = $unit->value;
            }
        }

        return array_values($units);
    }
}
