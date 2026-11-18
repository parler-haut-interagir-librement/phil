<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Contract\RetryRequestRepository;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\Availability;
use Psr\Clock\ClockInterface;

/**
 * Whether a learner can work on each activity of their course now: satisfied already; released later, by its module
 * or by itself, in the site's time zone; waiting for an earlier required activity in a course of imposed order; out of
 * tries, with a second chance to ask for or asked for; or open. No state is a dead end: a scheduled activity opens, a
 * locked one opens with the one before it, and a learner out of tries asks for a second chance or is waived.
 */
final readonly class Availabilities
{
    public function __construct(
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private RetryRequestRepository $retries,
        private ClockInterface $clock,
        private DateTimeZone $siteZone,
    ) {
    }

    /**
     * @return list<ActivityStatus> every activity of the course, module by module
     */
    public function outline(Enrollment $enrollment): array
    {
        $course = $this->courses->find($enrollment->course) ?? throw UnknownRecord::of('course', $enrollment->course->value);
        $modules = [];

        foreach ($this->courses->modules($course->id) as $module) {
            $modules[$module->id->value] = $module;
        }

        $progresses = $this->enrollments->progresses($enrollment->id);
        $now = $this->clock->now();
        $waitingFor = null;
        $statuses = [];

        foreach ($this->courses->activities($course->id) as $activity) {
            $module = $modules[$activity->module->value] ?? null;

            if (!$module instanceof Module) {
                continue;
            }

            $progress = $progresses[$activity->id->value] ?? null;
            $availability = $this->availability($enrollment, $module, $activity, $progress, $now, $course->ordered ? $waitingFor : null);
            $statuses[] = new ActivityStatus($activity, $module, $progress, $availability);

            if ($activity->required && null === $waitingFor && !($progress?->isSatisfied() ?? false)) {
                $waitingFor = $activity->id;
            }
        }

        return $statuses;
    }

    /**
     * @throws UnknownRecord when the activity is not one of the course
     */
    public function of(Enrollment $enrollment, ActivityId $activity): ActivityStatus
    {
        return array_find($this->outline($enrollment), static fn (ActivityStatus $status): bool => $status->activity->id->equals($activity))
            ?? throw UnknownRecord::of('activity', $activity->value);
    }

    private function availability(Enrollment $enrollment, Module $module, Activity $activity, ?Progress $progress, DateTimeImmutable $now, ?ActivityId $waitingFor): Availability
    {
        if ($progress?->isSatisfied() ?? false) {
            return Availability::satisfied();
        }

        $opensAt = $this->opensAt($enrollment, $module, $activity);

        if ($opensAt instanceof DateTimeImmutable && $now < $opensAt) {
            return Availability::scheduled($opensAt);
        }

        if ($waitingFor instanceof ActivityId) {
            return Availability::waitingFor($waitingFor);
        }

        if ($progress instanceof Progress && $progress->isOutOfTries($activity->maxAttempts)) {
            return Availability::outOfTries($this->retries->pending($enrollment->id, $activity->id) instanceof RetryRequest);
        }

        return Availability::open();
    }

    /**
     * The later of the moments the module and the activity open, null when both are open at once.
     */
    private function opensAt(Enrollment $enrollment, Module $module, Activity $activity): ?DateTimeImmutable
    {
        // A null, the release of what opens at once, is below any moment.
        return max(
            $module->release->opensAt($enrollment->enrolledAt, $this->siteZone),
            $activity->release->opensAt($enrollment->enrolledAt, $this->siteZone),
        );
    }
}
