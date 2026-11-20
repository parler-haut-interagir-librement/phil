<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use DateInterval;
use DateTimeImmutable;
use Phil\CoursesBundle\Application\Contract\TrainingNotifier;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Domain\Contract\AssignmentRepository;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Event\ObligationOverdue;
use Phil\CoursesBundle\Domain\Event\ObligationRenewed;
use Phil\CoursesBundle\Domain\Exception\CoursesException;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\ReminderStage;
use Phil\CoursesBundle\Domain\Training\TrainingNotice;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * One run of the mandatory training, from the scheduler or the console: the groups and units reach their new members;
 * each obligation not completed gets the reminder its due date calls for, once a cycle — before the due date, on it,
 * and overdue, the person who assigned the course told too —; and each completion whose renewal came opens a new
 * cycle, due a grace period after the renewal, the learner's enrollment starting again. The obligations are read a
 * batch at a time, so that a run holds a bounded number in memory.
 */
final readonly class TrainingRun
{
    public function __construct(
        private AssignmentRepository $assignments,
        private Assignments $training,
        private EnrollmentRepository $enrollmentStore,
        private Enrollments $enrollments,
        private TrainingSettings $settings,
        private ClockInterface $clock,
        private ?TrainingNotifier $notifier = null,
        private ?EventDispatcherInterface $events = null,
    ) {
    }

    public function run(): TrainingReport
    {
        [$reached, $skipped] = $this->synchronize();
        $now = $this->clock->now();
        $horizon = $now->add(new DateInterval(\sprintf('P%dD', $this->settings->reminderDays)));
        $reminded = 0;
        $renewed = 0;
        $after = null;

        do {
            $page = $this->assignments->actionable($horizon, $now, $this->settings->batch, $after);

            foreach ($page as $obligation) {
                if ($obligation->isRenewable($now)) {
                    $renewed += $this->renew($obligation);
                } else {
                    $reminded += $this->remind($obligation, $now);
                }

                $after = [$obligation->assignment, $obligation->learner];
            }
        } while (\count($page) === $this->settings->batch);

        return new TrainingReport($reached, $reminded, $renewed, $skipped);
    }

    /**
     * @return array{0: int, 1: int} the members reached and the assignments skipped
     */
    private function synchronize(): array
    {
        $reached = 0;
        $skipped = 0;
        $offset = 0;

        do {
            $page = $this->assignments->ofGroups($this->settings->batch, $offset);

            foreach ($page as $assignment) {
                try {
                    $reached += $this->training->synchronize($assignment);
                } catch (CoursesException) {
                    ++$skipped;
                }
            }

            $offset += $this->settings->batch;
        } while (\count($page) === $this->settings->batch);

        return [$reached, $skipped];
    }

    private function remind(Obligation $obligation, DateTimeImmutable $now): int
    {
        $stage = $obligation->stage($now, $this->settings->reminderDays);

        if (!$stage instanceof ReminderStage || !$obligation->remind($stage)) {
            return 0;
        }

        $this->assignments->saveObligation($obligation);
        $this->notifier?->notify(TrainingNotice::of($stage), $obligation->learner->value, $obligation);

        if (ReminderStage::Overdue === $stage) {
            $by = $this->assignments->find($obligation->assignment)?->assignedBy;

            if ($by instanceof LearnerId) {
                $this->notifier?->notify(TrainingNotice::OverdueManager, $by->value, $obligation);
            }

            $this->events?->dispatch(new ObligationOverdue($obligation->assignment->value, $obligation->learner->value, $obligation->course->value));
        }

        return 1;
    }

    private function renew(Obligation $obligation): int
    {
        if (!$obligation->renew($this->settings->graceDays)) {
            return 0;
        }

        $enrollment = $this->enrollmentStore->findFor($obligation->course, $obligation->learner);

        if ($enrollment instanceof Enrollment) {
            $this->enrollments->renew($enrollment);
        }

        $this->assignments->saveObligation($obligation);
        $this->notifier?->notify(TrainingNotice::Renewed, $obligation->learner->value, $obligation);
        $this->events?->dispatch(new ObligationRenewed($obligation->assignment->value, $obligation->learner->value, $obligation->course->value));

        return 1;
    }
}
