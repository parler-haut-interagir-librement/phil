<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\CoursesBundle\Domain\Contract\AssignmentRepository;
use Phil\CoursesBundle\Domain\Model\Assignment;
use Phil\CoursesBundle\Domain\Model\Obligation;
use Phil\CoursesBundle\Domain\Training\AssigneeKind;
use Phil\CoursesBundle\Domain\Training\ReminderStage;
use Phil\CoursesBundle\Domain\ValueObject\AssignmentId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The assignments and the obligations through DBAL. The reminders of an obligation are kept as their names, separated
 * by commas.
 */
final readonly class DbalAssignmentRepository implements AssignmentRepository
{
    private const array MOMENTS = ['due_at' => Types::DATETIME_UTC_IMMUTABLE, 'assigned_at' => Types::DATETIME_UTC_IMMUTABLE, 'completed_at' => Types::DATETIME_UTC_IMMUTABLE, 'renews_at' => Types::DATETIME_UTC_IMMUTABLE];

    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(Assignment $assignment): void
    {
        $this->rows->put(CourseTables::ASSIGNMENT, ['id' => $assignment->id->value], [
            'course_id' => $assignment->course->value,
            'kind' => $assignment->kind->value,
            'assignee' => $assignment->assignee,
            'due_at' => $assignment->dueAt,
            'renew_months' => $assignment->renewMonths,
            'assigned_by' => $assignment->assignedBy?->value,
            'assigned_at' => $assignment->assignedAt,
        ], self::MOMENTS);
    }

    #[Override]
    public function find(AssignmentId $id): ?Assignment
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::ASSIGNMENT.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->assignment($row);
    }

    #[Override]
    public function remove(AssignmentId $id): void
    {
        $this->connection->delete(CourseTables::OBLIGATION, ['assignment_id' => $id->value]);
        $this->connection->delete(CourseTables::ASSIGNMENT, ['id' => $id->value]);
    }

    #[Override]
    public function ofGroups(int $limit, int $offset = 0): array
    {
        return $this->assignments('SELECT * FROM '.CourseTables::ASSIGNMENT.' WHERE kind <> ? ORDER BY assigned_at, id LIMIT '.max(1, $limit).' OFFSET '.max(0, $offset), [AssigneeKind::Learner->value]);
    }

    #[Override]
    public function ofCourse(CourseId $course): array
    {
        return $this->assignments('SELECT * FROM '.CourseTables::ASSIGNMENT.' WHERE course_id = ? ORDER BY assigned_at, id', [$course->value]);
    }

    #[Override]
    public function to(AssigneeKind $kind, string $assignee): array
    {
        return $this->assignments('SELECT * FROM '.CourseTables::ASSIGNMENT.' WHERE kind = ? AND assignee = ? ORDER BY assigned_at, id', [$kind->value, $assignee]);
    }

    #[Override]
    public function saveObligation(Obligation $obligation): void
    {
        $this->rows->put(CourseTables::OBLIGATION, ['assignment_id' => $obligation->assignment->value, 'learner' => $obligation->learner->value], [
            'course_id' => $obligation->course->value,
            'cycle' => $obligation->cycle,
            'due_at' => $obligation->dueAt,
            'assigned_at' => $obligation->assignedAt,
            'completed_at' => $obligation->completedAt,
            'renews_at' => $obligation->renewsAt,
            'reminders' => implode(',', array_map(static fn (ReminderStage $stage): string => $stage->value, $obligation->reminders)),
        ], self::MOMENTS);
    }

    #[Override]
    public function obligation(AssignmentId $assignment, LearnerId $learner): ?Obligation
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::OBLIGATION.' WHERE assignment_id = ? AND learner = ?', [$assignment->value, $learner->value]);

        return false === $row ? null : $this->obligationOf($row);
    }

    #[Override]
    public function obligationsOf(LearnerId $learner): array
    {
        return $this->obligations('SELECT * FROM '.CourseTables::OBLIGATION.' WHERE learner = ? ORDER BY due_at, assignment_id', [$learner->value]);
    }

    #[Override]
    public function obligationsFor(CourseId $course, LearnerId $learner): array
    {
        return $this->obligations('SELECT * FROM '.CourseTables::OBLIGATION.' WHERE learner = ? AND course_id = ? ORDER BY due_at, assignment_id', [$learner->value, $course->value]);
    }

    #[Override]
    public function actionable(DateTimeImmutable $horizon, DateTimeImmutable $now, int $limit, ?array $after = null): array
    {
        $sql = 'SELECT * FROM '.CourseTables::OBLIGATION.' WHERE ((completed_at IS NULL AND due_at <= ?) OR renews_at <= ?)';
        $parameters = [$horizon, $now];
        $types = [Types::DATETIME_UTC_IMMUTABLE, Types::DATETIME_UTC_IMMUTABLE];

        if (null !== $after) {
            $sql .= ' AND (assignment_id > ? OR (assignment_id = ? AND learner > ?))';
            $parameters = [...$parameters, $after[0]->value, $after[0]->value, $after[1]->value];
            $types = [...$types, Types::STRING, Types::STRING, Types::STRING];
        }

        return $this->obligations($sql.' ORDER BY assignment_id, learner LIMIT '.max(1, $limit), $parameters, $types);
    }

    #[Override]
    public function dueBy(DateTimeImmutable $horizon, int $limit, int $offset = 0): array
    {
        return $this->obligations(
            'SELECT * FROM '.CourseTables::OBLIGATION.' WHERE completed_at IS NULL AND due_at <= ? ORDER BY due_at, assignment_id, learner LIMIT '.max(1, $limit).' OFFSET '.max(0, $offset),
            [$horizon],
            [Types::DATETIME_UTC_IMMUTABLE],
        );
    }

    #[Override]
    public function removeOf(LearnerId $learner): void
    {
        $this->connection->delete(CourseTables::OBLIGATION, ['learner' => $learner->value]);
        $this->connection->update(CourseTables::ASSIGNMENT, ['assigned_by' => null], ['assigned_by' => $learner->value]);
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<Assignment>
     */
    private function assignments(string $sql, array $parameters): array
    {
        return array_map($this->assignment(...), $this->connection->fetchAllAssociative($sql, $parameters));
    }

    /**
     * @param list<mixed>  $parameters
     * @param list<string> $types
     *
     * @return list<Obligation>
     */
    private function obligations(string $sql, array $parameters, array $types = []): array
    {
        return array_map($this->obligationOf(...), $this->connection->fetchAllAssociative($sql, $parameters, $types));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function assignment(array $row): Assignment
    {
        $by = Rows::nullableText($row['assigned_by']);
        $months = Rows::nullableInt($row['renew_months']);

        return new Assignment(
            AssignmentId::of(Rows::text($row['id'])),
            CourseId::of(Rows::text($row['course_id'])),
            AssigneeKind::from(Rows::text($row['kind'])),
            Rows::text($row['assignee']),
            $this->rows->requiredMoment($row['due_at']),
            $this->rows->requiredMoment($row['assigned_at']),
            $months,
            null === $by ? null : LearnerId::of($by),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function obligationOf(array $row): Obligation
    {
        $reminders = Rows::text($row['reminders']);

        return new Obligation(
            AssignmentId::of(Rows::text($row['assignment_id'])),
            CourseId::of(Rows::text($row['course_id'])),
            LearnerId::of(Rows::text($row['learner'])),
            $this->rows->requiredMoment($row['due_at']),
            $this->rows->requiredMoment($row['assigned_at']),
            max(1, Rows::int($row['cycle'])),
            $this->rows->moment($row['completed_at']),
            $this->rows->moment($row['renews_at']),
            '' === $reminders ? [] : array_values(array_filter(array_map(ReminderStage::tryFrom(...), explode(',', $reminders)))),
        );
    }
}
