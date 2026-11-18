<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\ValueObject\Availability;

/**
 * An activity as a learner sees it: its module, their progress, and whether they can work on it now.
 */
final readonly class ActivityStatus
{
    public function __construct(
        public Activity $activity,
        public Module $module,
        public ?Progress $progress,
        public Availability $availability,
    ) {
    }
}
