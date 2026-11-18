<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use Phil\CoursesBundle\Domain\Model\ScormAttempt;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;

/**
 * Where the SCORM attempts and their tracking data are kept.
 */
interface ScormAttemptRepository
{
    public function save(ScormAttempt $attempt): void;

    public function find(ScormAttemptId $id): ?ScormAttempt;

    /**
     * The learner's last attempt at the activity, by its number.
     */
    public function latest(EnrollmentId $enrollment, ActivityId $activity): ?ScormAttempt;

    /**
     * Every attempt of the enrollments, by enrollment, then activity, then number.
     *
     * @return list<ScormAttempt>
     */
    public function ofEnrollments(EnrollmentId ...$enrollments): array;

    /**
     * Removes every attempt of the enrollments, with its tracking data.
     */
    public function removeOfEnrollments(EnrollmentId ...$enrollments): void;
}
