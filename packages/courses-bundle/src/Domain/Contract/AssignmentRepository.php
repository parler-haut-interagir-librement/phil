<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Model\Assignment;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The assignments of the courses and each learner's obligation under them.
 */
interface AssignmentRepository
{
    public function save(Assignment $assignment): void;

    public function find(AssignmentId $id): ?Assignment;

    /**
     * Removes the assignment and the obligations under it.
     */
    public function remove(AssignmentId $id): void;

    /**
     * @return list<Assignment> the assignments to a group or a unit, the oldest first, a page at a time
     */
    public function ofGroups(int $limit, int $offset = 0): array;

    /**
     * @return list<Assignment> the assignments of the course, the oldest first
     */
    public function ofCourse(CourseId $course): array;

    /**
     * @return list<Assignment> the assignments to that learner, group or unit
     */
    public function to(AssigneeKind $kind, string $assignee): array;

    public function saveObligation(Obligation $obligation): void;

    public function obligation(AssignmentId $assignment, LearnerId $learner): ?Obligation;

    /**
     * @return list<Obligation> the learner's obligations, the earliest due first
     */
    public function obligationsOf(LearnerId $learner): array;

    /**
     * @return list<Obligation> the learner's obligations under the assignments of that course, completed or not
     */
    public function obligationsFor(CourseId $course, LearnerId $learner): array;

    /**
     * The obligations a run acts on — not completed and due by the horizon, or completed and renewed by the moment —
     * a page at a time, in the order of their assignment and learner, after the key of the last one of the page before.
     *
     * @param ?array{0: AssignmentId, 1: LearnerId} $after
     *
     * @return list<Obligation>
     */
    public function actionable(DateTimeImmutable $horizon, DateTimeImmutable $now, int $limit, ?array $after = null): array;

    /**
     * @return list<Obligation> the obligations not completed and due by the horizon, the earliest due first, a page at a time
     */
    public function dueBy(DateTimeImmutable $horizon, int $limit, int $offset = 0): array;

    /**
     * Removes the learner's obligations, and their name from the assignments they made: they are kept unsigned.
     */
    public function removeOf(LearnerId $learner): void;
}
