<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Training;

use DateInterval;
use DateTimeImmutable;
use Phil\CoursesBundle\Application\Contract\AssigneeMembers;
use Phil\CoursesBundle\Application\Contract\TrainingNotifier;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\Equivalences;
use Phil\CoursesBundle\Domain\Contract\AssignmentRepository;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Event\TrainingAssigned;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Assignment;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\Training\ObligationState;
use Phil\CoursesBundle\Domain\Training\TrainingNotice;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * The courses a learner must take by a due date: assigned to a learner, or to the members of a group or a unit, each
 * member enrolled and given an obligation, and told; a member who completed the course already starts completed. A
 * group or a unit gains its new members at each run; a member who left keeps their obligation. A completion of the
 * course completes the learner's obligations under it, and sets when a recertification renews it. With the groups of
 * equivalent courses, a completion of an equivalent course counts as one of the course: it completes the obligations
 * under every course of the group, and a member who completed an equivalent course already starts completed.
 */
final readonly class Assignments
{
    /**
     * @param iterable<AssigneeMembers> $members
     */
    public function __construct(
        private AssignmentRepository $assignments,
        private CourseRepository $courses,
        private Enrollments $enrollments,
        private TrainingSettings $settings,
        private ClockInterface $clock,
        private iterable $members = [],
        private ?TrainingNotifier $notifier = null,
        private ?EventDispatcherInterface $events = null,
        private ?Equivalences $equivalences = null,
    ) {
    }

    /**
     * @param ?int       $renewMonths after how many months a completion is renewed; null for a course taken once
     * @param ?LearnerId $by          who assigns it, told when it is overdue
     *
     * @throws UnknownRecord when the course is not known
     * @throws InvalidCourse when a value is wrong, no adapter names the members of a group or a unit, or they are too many
     */
    public function assign(CourseId $course, AssigneeKind $kind, string $assignee, DateTimeImmutable $dueAt, ?int $renewMonths = null, ?LearnerId $by = null): Assignment
    {
        $this->courses->find($course) ?? throw UnknownRecord::of('course', $course->value);
        $assignment = new Assignment(AssignmentId::generate(), $course, $kind, $assignee, $dueAt, $this->clock->now(), $renewMonths, $by);
        $learners = $this->learners($assignment);
        $this->assignments->save($assignment);
        $this->events?->dispatch(new TrainingAssigned($assignment->id->value, $course->value));
        $this->reach($assignment, $learners);

        return $assignment;
    }

    /**
     * Gives an obligation to the members of the group or unit who have none yet.
     *
     * @return int how many members it reached
     *
     * @throws InvalidCourse when no adapter names the members, or they are too many
     */
    public function synchronize(Assignment $assignment): int
    {
        return $this->reach($assignment, $this->learners($assignment));
    }

    /**
     * @throws UnknownRecord when the assignment is not known
     */
    public function get(AssignmentId $id): Assignment
    {
        return $this->assignments->find($id) ?? throw UnknownRecord::of('assignment', $id->value);
    }

    public function remove(AssignmentId $id): void
    {
        $this->assignments->remove($id);
    }

    /**
     * @return list<TrainingStatus> the learner's assigned courses, the earliest due first
     */
    public function of(LearnerId $learner): array
    {
        return $this->statuses($this->assignments->obligationsOf($learner));
    }

    /**
     * @return list<TrainingStatus> the obligations overdue or due soon, the earliest due first, a page at a time
     */
    public function attention(int $limit = 200, int $offset = 0): array
    {
        $horizon = $this->clock->now()->add(new DateInterval(\sprintf('P%dD', $this->settings->reminderDays)));

        return $this->statuses($this->assignments->dueBy($horizon, $limit, $offset));
    }

    /**
     * Completes the learner's obligations under the course they completed at that moment, and under its equivalent
     * courses.
     *
     * @return list<Obligation> the obligations it completed
     */
    public function completed(Enrollment $enrollment, DateTimeImmutable $at): array
    {
        $completed = [];
        $obligations = [];

        foreach ([$enrollment->course, ...($this->equivalences?->equivalents($enrollment->course) ?? [])] as $course) {
            array_push($obligations, ...$this->assignments->obligationsFor($course, $enrollment->learner));
        }

        foreach ($obligations as $obligation) {
            $assignment = $this->assignments->find($obligation->assignment);

            if ($assignment instanceof Assignment && $obligation->complete($at, $this->renewal($assignment, $at))) {
                $this->assignments->saveObligation($obligation);
                $completed[] = $obligation;
            }
        }

        return $completed;
    }

    /**
     * @param list<Obligation> $obligations
     *
     * @return list<TrainingStatus>
     */
    private function statuses(array $obligations): array
    {
        $now = $this->clock->now();
        $statuses = [];

        foreach ($obligations as $obligation) {
            $course = $this->courses->find($obligation->course);

            if ($course instanceof Course) {
                $statuses[] = new TrainingStatus($course, $obligation, $obligation->state($now, $this->settings->reminderDays));
            }
        }

        return $statuses;
    }

    /**
     * @param array<LearnerId> $learners
     */
    private function reach(Assignment $assignment, array $learners): int
    {
        $now = $this->clock->now();
        $reached = 0;

        foreach ($learners as $learner) {
            if ($this->assignments->obligation($assignment->id, $learner) instanceof Obligation) {
                continue;
            }

            $enrollment = $this->enrollments->enroll($assignment->course, $learner);
            $obligation = new Obligation($assignment->id, $assignment->course, $learner, $assignment->dueAt, $now);
            $completedAt = $enrollment->completedAt ?? $this->equivalentCompletion($assignment->course, $learner);

            if ($completedAt instanceof DateTimeImmutable) {
                $obligation->complete($completedAt, $this->renewal($assignment, $completedAt));
            }

            $this->assignments->saveObligation($obligation);
            ++$reached;

            if (ObligationState::Completed !== $obligation->state($now, $this->settings->reminderDays)) {
                $this->notifier?->notify(TrainingNotice::Assigned, $learner->value, $obligation);
            }
        }

        return $reached;
    }

    /**
     * The latest moment the learner completed an equivalent course of this one, or null.
     */
    private function equivalentCompletion(CourseId $course, LearnerId $learner): ?DateTimeImmutable
    {
        $latest = null;

        foreach ($this->equivalences?->equivalents($course) ?? [] as $equivalent) {
            $completedAt = $this->enrollments->of($equivalent, $learner)?->completedAt;

            if ($completedAt instanceof DateTimeImmutable && (!$latest instanceof DateTimeImmutable || $completedAt > $latest)) {
                $latest = $completedAt;
            }
        }

        return $latest;
    }

    private function renewal(Assignment $assignment, DateTimeImmutable $completedAt): ?DateTimeImmutable
    {
        return null === $assignment->renewMonths ? null : Recertification::renewsAt($completedAt, $assignment->renewMonths);
    }

    /**
     * @return array<LearnerId> the members, each once
     */
    private function learners(Assignment $assignment): array
    {
        if (AssigneeKind::Learner === $assignment->kind) {
            return [LearnerId::of($assignment->assignee)];
        }

        foreach ($this->members as $members) {
            if (!$members->supports($assignment->kind)) {
                continue;
            }

            $learners = [];

            foreach ($members->members($assignment->kind, $assignment->assignee) as $member) {
                if (\count($learners) === $this->settings->maxMembers) {
                    throw InvalidCourse::because(\sprintf('An assignment reaches %d members at most.', $this->settings->maxMembers));
                }

                $learners[$member] = LearnerId::of($member);
            }

            return $learners;
        }

        throw InvalidCourse::because(\sprintf('No AssigneeMembers service names the members of a %s.', $assignment->kind->value));
    }
}
