<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Progress;

/**
 * Where an activity stands for a learner.
 */
enum AvailabilityStatus: string
{
    /** The learner can work on it. */
    case Open = 'open';

    /** Done as its requirement asks; the learner can still revisit it. */
    case Satisfied = 'satisfied';

    /** Released later, by date or by days after the enrollment. */
    case Scheduled = 'scheduled';

    /** In a course of imposed order, an earlier required activity is not satisfied yet. */
    case Locked = 'locked';

    /** Every try was failed: the learner can ask for a second chance. */
    case RetryNeeded = 'retry_needed';

    /** Every try was failed and a second chance was asked for, not decided yet. */
    case RetryRequested = 'retry_requested';
}
