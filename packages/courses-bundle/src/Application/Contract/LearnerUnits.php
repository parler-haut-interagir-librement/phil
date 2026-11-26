<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The units of an organization whose catalogues reach a learner: the units they belong to and the units above them.
 * With phil/organizations-bundle, the bundle answers it from the tree of units; an application without it names its
 * own, or none, and the learners see the public catalogues only.
 */
interface LearnerUnits
{
    /**
     * @return list<string> the identifiers of the units, each once
     */
    public function units(LearnerId $learner): array;
}
