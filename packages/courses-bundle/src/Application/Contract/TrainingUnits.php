<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The organisational unit the training of a learner counts in, as the training reports of phil/reporting-bundle total
 * it. With phil/organizations-bundle, the bundle answers it from the tree of units; without one, every completion is
 * reported without a unit.
 */
interface TrainingUnits
{
    /**
     * The identifier of the learner's unit: 1 to 128 printable ASCII characters; null when the learner belongs to none.
     */
    public function unitOf(LearnerId $learner): ?string;
}
