<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\Model\RetryRequest;

/**
 * A request for a second chance as a teacher reviews it: the request, the enrollment, the course and the activity it is
 * about, and the learner's progress in that activity — the tries failed and granted.
 */
final readonly class RetryReview
{
    public function __construct(
        public RetryRequest $request,
        public Enrollment $enrollment,
        public Course $course,
        public Activity $activity,
        public ?Progress $progress,
    ) {
    }

    /**
     * How many tries the learner had: the activity's tries and the second chances granted; null for no limit.
     */
    public function tries(): ?int
    {
        return null === $this->activity->maxAttempts ? null : $this->activity->maxAttempts + ($this->progress->extraTries ?? 0);
    }

    public function failures(): int
    {
        return $this->progress->failures ?? 0;
    }
}
