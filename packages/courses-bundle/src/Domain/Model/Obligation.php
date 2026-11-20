<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Model;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Training\ObligationState;
use Phil\CoursesBundle\Domain\Training\ReminderStage;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * One learner's share of an assignment: the cycle — the first, then one more at each renewal —, its due moment, the
 * deadline the course is completed before, when the learner completed the course in this cycle, when that completion is renewed, and the reminders sent in this
 * cycle. Moments are kept in UTC.
 */
final class Obligation
{
    public private(set) DateTimeImmutable $dueAt;

    public readonly DateTimeImmutable $assignedAt;

    public private(set) ?DateTimeImmutable $completedAt;

    public private(set) ?DateTimeImmutable $renewsAt;

    /**
     * @param int<1, max>         $cycle
     * @param list<ReminderStage> $reminders the reminders sent in this cycle
     */
    public function __construct(
        public readonly AssignmentId $assignment,
        public readonly CourseId $course,
        public readonly LearnerId $learner,
        DateTimeImmutable $dueAt,
        DateTimeImmutable $assignedAt,
        public private(set) int $cycle = 1,
        ?DateTimeImmutable $completedAt = null,
        ?DateTimeImmutable $renewsAt = null,
        public private(set) array $reminders = [],
    ) {
        $utc = new DateTimeZone('UTC');
        $this->dueAt = $dueAt->setTimezone($utc);
        $this->assignedAt = $assignedAt->setTimezone($utc);
        $this->completedAt = $completedAt?->setTimezone($utc);
        $this->renewsAt = $renewsAt?->setTimezone($utc);
    }

    /**
     * Where the learner stands at that moment, the reminder window that many days before the due date.
     */
    public function state(DateTimeImmutable $now, int $soonDays): ObligationState
    {
        return match (true) {
            $this->completedAt instanceof DateTimeImmutable => ObligationState::Completed,
            $now >= $this->dueAt => ObligationState::Overdue,
            $now >= $this->dueAt->sub(new DateInterval(\sprintf('P%dD', $soonDays))) => ObligationState::DueSoon,
            default => ObligationState::Pending,
        };
    }

    /**
     * The reminder this moment calls for, sent or not: none once completed or before the window, then before the due
     * moment, in its last day, and overdue once it passed.
     */
    public function stage(DateTimeImmutable $now, int $soonDays): ?ReminderStage
    {
        return match (true) {
            $this->completedAt instanceof DateTimeImmutable => null,
            $now >= $this->dueAt => ReminderStage::Overdue,
            $now >= $this->dueAt->sub(new DateInterval('P1D')) => ReminderStage::AtDue,
            $now >= $this->dueAt->sub(new DateInterval(\sprintf('P%dD', $soonDays))) => ReminderStage::BeforeDue,
            default => null,
        };
    }

    /**
     * Notes that a reminder is sent: says whether it was not sent yet in this cycle.
     */
    public function remind(ReminderStage $stage): bool
    {
        if (\in_array($stage, $this->reminders, true)) {
            return false;
        }

        $this->reminders[] = $stage;

        return true;
    }

    /**
     * Notes the completion of this cycle, and when it is renewed: says whether the cycle was not completed yet.
     */
    public function complete(DateTimeImmutable $at, ?DateTimeImmutable $renewsAt): bool
    {
        if ($this->completedAt instanceof DateTimeImmutable) {
            return false;
        }

        $this->completedAt = $at->setTimezone(new DateTimeZone('UTC'));
        $this->renewsAt = $renewsAt?->setTimezone(new DateTimeZone('UTC'));

        return true;
    }

    /**
     * Whether the completion of this cycle is due for renewal at that moment.
     */
    public function isRenewable(DateTimeImmutable $now): bool
    {
        return $this->renewsAt instanceof DateTimeImmutable && $now >= $this->renewsAt;
    }

    /**
     * Opens the next cycle, due that many days after the renewal: the completion and the reminders of the cycle before
     * are forgotten. Says whether the cycle had a renewal to open the next one from.
     */
    public function renew(int $graceDays): bool
    {
        if (!$this->renewsAt instanceof DateTimeImmutable) {
            return false;
        }

        ++$this->cycle;
        $this->dueAt = $this->renewsAt->add(new DateInterval(\sprintf('P%dD', $graceDays)));
        $this->completedAt = null;
        $this->renewsAt = null;
        $this->reminders = [];

        return true;
    }
}
