<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use Phil\CoursesBundle\Domain\Exception\InvalidCourse;

/**
 * The settings of the mandatory training: how many days before the due date a course is due soon and its first
 * reminder sent, how many days a renewed course gives before it is due again, how many members of a group or unit an
 * assignment reaches, and how many obligations a run reads at once.
 */
final readonly class TrainingSettings
{
    public const int REMINDER_DAYS = 7;

    public const int GRACE_DAYS = 30;

    public const int MAX_MEMBERS = 10_000;

    public const int BATCH = 500;

    /**
     * @throws InvalidCourse when a number is outside its bounds
     */
    public function __construct(
        public int $reminderDays = self::REMINDER_DAYS,
        public int $graceDays = self::GRACE_DAYS,
        public int $maxMembers = self::MAX_MEMBERS,
        public int $batch = self::BATCH,
    ) {
        if ($reminderDays < 1 || $reminderDays > 90 || $graceDays < 1 || $graceDays > 365) {
            throw InvalidCourse::because('A reminder comes 1 to 90 days before the due date, and a renewed course is due 1 to 365 days later.');
        }

        if ($maxMembers < 1 || $batch < 1) {
            throw InvalidCourse::because('The members of an assignment and the obligations of a batch are counted from 1.');
        }
    }
}
