<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * A course a learner, a group or a unit must take by a due date: who it is assigned to, by whom, the first due date,
 * and, for a recertification, after how many months a completion is renewed. Moments are kept in UTC.
 */
final class Assignment
{
    public const int MAX_RENEWAL_MONTHS = 120;

    public readonly DateTimeImmutable $dueAt;

    public readonly DateTimeImmutable $assignedAt;

    public readonly string $assignee;

    /**
     * @param ?int $renewMonths after how many months a completion is renewed, 1 to 120; null for a course taken once
     *
     * @throws InvalidCourse when the assignee is not an identifier, the renewal outside its bounds, or the due date
     *                       before the assignment
     */
    public function __construct(
        public readonly AssignmentId $id,
        public readonly CourseId $course,
        public readonly AssigneeKind $kind,
        string $assignee,
        DateTimeImmutable $dueAt,
        DateTimeImmutable $assignedAt,
        public readonly ?int $renewMonths = null,
        public readonly ?LearnerId $assignedBy = null,
    ) {
        $this->assignee = Identifiers::check('An assignee', $assignee, LearnerId::MAX_LENGTH);

        if (null !== $renewMonths && ($renewMonths < 1 || $renewMonths > self::MAX_RENEWAL_MONTHS)) {
            throw InvalidCourse::because(\sprintf('A course is renewed after 1 to %d months.', self::MAX_RENEWAL_MONTHS));
        }

        if ($dueAt < $assignedAt) {
            throw InvalidCourse::because('The due date of an assignment is not before the assignment.');
        }

        $this->dueAt = $dueAt->setTimezone(new DateTimeZone('UTC'));
        $this->assignedAt = $assignedAt->setTimezone(new DateTimeZone('UTC'));
    }
}
