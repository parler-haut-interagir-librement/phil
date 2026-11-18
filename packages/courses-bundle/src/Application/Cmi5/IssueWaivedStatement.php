<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use Phil\CoursesBundle\Application\ActivityType\Cmi5Activity;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Psr\Clock\ClockInterface;

/**
 * Issues the `waived` statement of a cmi5 unit a teacher waived, in a session of its own, as cmi5 asks, then the
 * `satisfied` statements of the blocks, and of the course, the waiver satisfied.
 */
final readonly class IssueWaivedStatement
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private CourseRepository $courses,
        private Cmi5Statements $statements,
        private Cmi5Settings $settings,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ActivitySatisfied $event): void
    {
        if (!$event->waived) {
            return;
        }

        $activity = $this->courses->activity(ActivityId::of($event->activity));
        $enrollment = $this->enrollments->find(EnrollmentId::of($event->enrollment));

        if (!$activity instanceof Activity || Cmi5Activity::NAME !== $activity->type || !$enrollment instanceof Enrollment) {
            return;
        }

        $now = $this->clock->now();
        $session = new LaunchSession(LaunchSessionId::generate(), $enrollment->id, $activity->id, $now, $now);
        $context = Cmi5Context::of($session, $enrollment, $activity, $this->settings->homePage());
        $this->statements->issue(PlatformStatements::waived($context, $now), $enrollment->registration, $session->id->value);
        $this->statements->satisfy($context, $enrollment);
    }
}
