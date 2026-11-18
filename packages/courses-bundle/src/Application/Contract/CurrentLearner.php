<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * Who takes the courses on the pages: the learner signed in, or null.
 */
interface CurrentLearner
{
    public function current(): ?LearnerId;
}
