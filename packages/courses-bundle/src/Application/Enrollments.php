<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Event\ActivityProgressed;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Event\EnrollmentRenewed;
use Phil\CoursesBundle\Domain\Event\LearnerEnrolled;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The enrollments and the progress: a learner enrolls once in a course; each outcome updates their progress in an
 * activity, satisfies it when its requirement is met, and completes the course when every required activity is
 * satisfied. Each change is told to the event dispatcher.
 */
final readonly class Enrollments
{
    public function __construct(
        private CourseRepository $courses,
        private EnrollmentRepository $enrollments,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    /**
     * Enrolls the learner, or gives back the enrollment they have.
     *
     * @throws UnknownRecord when the course is not known
     */
    public function enroll(CourseId $course, LearnerId $learner): Enrollment
    {
        $this->courses->find($course) ?? throw UnknownRecord::of('course', $course->value);
        $enrollment = $this->enrollments->findFor($course, $learner);

        if ($enrollment instanceof Enrollment) {
            return $enrollment;
        }

        $enrollment = new Enrollment(EnrollmentId::generate(), $course, $learner, $this->clock->now());
        $this->enrollments->save($enrollment);
        $this->events?->dispatch(new LearnerEnrolled($enrollment->id->value, $course->value, $learner->value));

        return $enrollment;
    }

    /**
     * @throws UnknownRecord when the enrollment is not known
     */
    public function get(EnrollmentId $id): Enrollment
    {
        return $this->enrollments->find($id) ?? throw UnknownRecord::of('enrollment', $id->value);
    }

    public function of(CourseId $course, LearnerId $learner): ?Enrollment
    {
        return $this->enrollments->findFor($course, $learner);
    }

    /**
     * @return array<string, Progress> by activity identifier
     */
    public function progresses(Enrollment $enrollment): array
    {
        return $this->enrollments->progresses($enrollment->id);
    }

    /**
     * Notes that the learner opened the activity; one without a requirement is satisfied by it.
     */
    public function start(Enrollment $enrollment, Activity $activity): Progress
    {
        $now = $this->clock->now();
        $progress = $this->progress($enrollment, $activity, $now);
        $progress->start($now);

        return $this->changed($enrollment, $activity, $progress, $progress->satisfy($activity->requirement, $now));
    }

    /**
     * Applies an outcome to the learner's progress in the activity.
     *
     * @param ?DateTimeImmutable $at when it happened, for a result imported from another platform; now by default
     */
    public function record(Enrollment $enrollment, Activity $activity, Outcome $outcome, ?DateTimeImmutable $at = null): Progress
    {
        $at ??= $this->clock->now();
        $progress = $this->progress($enrollment, $activity, $at);

        return $this->changed($enrollment, $activity, $progress, $progress->apply($outcome, $activity->requirement, $at));
    }

    /**
     * Waives the activity for the learner: the course counts it satisfied.
     */
    public function waive(Enrollment $enrollment, Activity $activity): Progress
    {
        $now = $this->clock->now();
        $progress = $this->progress($enrollment, $activity, $now);

        return $this->changed($enrollment, $activity, $progress, $progress->waive($now), true);
    }

    /**
     * Grants the learner one more try at the activity.
     */
    public function grantTry(Enrollment $enrollment, Activity $activity): Progress
    {
        $now = $this->clock->now();
        $progress = $this->progress($enrollment, $activity, $now);
        $progress->grantTry($now);

        return $this->changed($enrollment, $activity, $progress, false);
    }

    /**
     * Starts the enrollment again, for a recertification: its progress in every activity is forgotten, the `satisfied`
     * statements it was issued may be issued again, and its completion is undone; its registration stays.
     */
    public function renew(Enrollment $enrollment): void
    {
        $this->enrollments->removeProgresses($enrollment->id);

        foreach ($this->enrollments->satisfactions($enrollment->id) as $subject) {
            $this->enrollments->releaseSatisfaction($enrollment->id, $subject);
        }

        $enrollment->reopen();
        $this->enrollments->save($enrollment);
        $this->events?->dispatch(new EnrollmentRenewed($enrollment->id->value, $enrollment->course->value, $enrollment->learner->value));
    }

    /**
     * The share of the required activities the learner satisfied, from 0 to 100.
     */
    public function percent(Enrollment $enrollment): int
    {
        $required = array_filter($this->courses->activities($enrollment->course), static fn (Activity $activity): bool => $activity->required);

        if ([] === $required) {
            return $enrollment->isCompleted() ? 100 : 0;
        }

        $progresses = $this->enrollments->progresses($enrollment->id);
        $satisfied = array_filter($required, static fn (Activity $activity): bool => ($progresses[$activity->id->value] ?? null)?->isSatisfied() ?? false);

        return intdiv(100 * \count($satisfied), \count($required));
    }

    private function progress(Enrollment $enrollment, Activity $activity, DateTimeImmutable $at): Progress
    {
        return $this->enrollments->progress($enrollment->id, $activity->id) ?? new Progress($enrollment->id, $activity->id, $at);
    }

    private function changed(Enrollment $enrollment, Activity $activity, Progress $progress, bool $satisfied, bool $waived = false): Progress
    {
        $this->enrollments->saveProgress($progress);
        $this->events?->dispatch(new ActivityProgressed($enrollment->id->value, $activity->id->value));

        if ($satisfied) {
            $this->events?->dispatch(new ActivitySatisfied($enrollment->id->value, $activity->id->value, $waived));
            $this->completeIfDone($enrollment);
        }

        return $progress;
    }

    private function completeIfDone(Enrollment $enrollment): void
    {
        $progresses = $this->enrollments->progresses($enrollment->id);

        foreach ($this->courses->activities($enrollment->course) as $activity) {
            if ($activity->required && !(($progresses[$activity->id->value] ?? null)?->isSatisfied() ?? false)) {
                return;
            }
        }

        if ($enrollment->complete($this->clock->now())) {
            $this->enrollments->save($enrollment);
            $this->events?->dispatch(new CourseCompleted($enrollment->id->value, $enrollment->course->value, $enrollment->learner->value));
        }
    }
}
