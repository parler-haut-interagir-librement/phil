<?php

declare(strict_types=1);

namespace Phil\VirtualClassroomBundle\Infrastructure\Courses;

use DateTimeImmutable;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\VirtualClassroom\RoomId;
use Phil\VirtualClassroomBundle\Application\Classrooms;
use Phil\VirtualClassroomBundle\Domain\Event\AttendanceUpdated;

/**
 * Completes a live class for each enrolled learner who attended enough of its session: when the attendance of an
 * ended room of an activity changes, every participant whose share reaches the activity's `threshold` — the
 * configured one by default — has the activity recorded as completed, at the end of the room. A participant who is
 * not a learner of the course, or a stay reported later that adds nothing, changes nothing; a second run records
 * nothing new.
 */
final readonly class CoursesCompletion
{
    /**
     * @param int<1, 100> $threshold
     */
    public function __construct(
        private Classrooms $classrooms,
        private Courses $courses,
        private Enrollments $enrollments,
        private int $threshold,
    ) {
    }

    public function __invoke(AttendanceUpdated $event): void
    {
        $room = $this->classrooms->room(RoomId::fromString($event->room));

        if (!VirtualClassroomActivity::owns($room) || !$room->endedAt instanceof DateTimeImmutable || null === $room->owner) {
            return;
        }

        try {
            $activity = $this->courses->activity(ActivityId::of(substr($room->owner, \strlen(VirtualClassroomActivity::OWNER_PREFIX))));
        } catch (CoursesException) {
            return;
        }

        $threshold = $activity->setting('threshold');
        $threshold = \is_int($threshold) ? $threshold : $this->threshold;

        foreach ($this->classrooms->attendance($room->id) as $report) {
            if ($report->percent < $threshold) {
                continue;
            }

            try {
                $enrollment = $this->enrollments->of($activity->course, LearnerId::of($report->participant->value));

                if ($enrollment instanceof Enrollment) {
                    $this->enrollments->record($enrollment, $activity, Outcome::completed(), $room->endedAt);
                }
            } catch (CoursesException) {
                continue;
            }
        }
    }
}
