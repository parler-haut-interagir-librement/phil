<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\CoursesBundle\Domain\Contract\EnrollmentRepository;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Score;

/**
 * The enrollments and the progress through DBAL.
 */
final readonly class DbalEnrollmentRepository implements EnrollmentRepository
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(Enrollment $enrollment): void
    {
        $this->rows->put(CourseTables::ENROLLMENT, ['id' => $enrollment->id->value], [
            'course_id' => $enrollment->course->value,
            'learner' => $enrollment->learner->value,
            'registration' => $enrollment->registration,
            'enrolled_at' => $enrollment->enrolledAt,
            'completed_at' => $enrollment->completedAt,
        ], ['enrolled_at' => Types::DATETIME_UTC_IMMUTABLE, 'completed_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function find(EnrollmentId $id): ?Enrollment
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::ENROLLMENT.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->enrollment($row);
    }

    #[Override]
    public function findFor(CourseId $course, LearnerId $learner): ?Enrollment
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::ENROLLMENT.' WHERE course_id = ? AND learner = ?', [$course->value, $learner->value]);

        return false === $row ? null : $this->enrollment($row);
    }

    #[Override]
    public function ofLearner(LearnerId $learner): array
    {
        return array_map($this->enrollment(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::ENROLLMENT.' WHERE learner = ? ORDER BY enrolled_at DESC, id DESC',
            [$learner->value],
        ));
    }

    #[Override]
    public function removeProgresses(EnrollmentId $enrollment): void
    {
        $this->connection->delete(CourseTables::PROGRESS, ['enrollment_id' => $enrollment->value]);
    }

    #[Override]
    public function progress(EnrollmentId $enrollment, ActivityId $activity): ?Progress
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::PROGRESS.' WHERE enrollment_id = ? AND activity_id = ?', [$enrollment->value, $activity->value]);

        return false === $row ? null : $this->progressOf($row);
    }

    #[Override]
    public function progresses(EnrollmentId $enrollment): array
    {
        $progresses = [];

        foreach ($this->connection->fetchAllAssociative('SELECT * FROM '.CourseTables::PROGRESS.' WHERE enrollment_id = ?', [$enrollment->value]) as $row) {
            $progress = $this->progressOf($row);
            $progresses[$progress->activity->value] = $progress;
        }

        return $progresses;
    }

    #[Override]
    public function satisfactions(EnrollmentId $enrollment): array
    {
        return array_map(Rows::text(...), $this->connection->fetchFirstColumn('SELECT subject_id FROM '.CourseTables::SATISFACTION.' WHERE enrollment_id = ? ORDER BY satisfied_at, subject_id', [$enrollment->value]));
    }

    #[Override]
    public function claimSatisfaction(EnrollmentId $enrollment, string $subject, string $statement, DateTimeImmutable $at): bool
    {
        try {
            $this->connection->insert(CourseTables::SATISFACTION, [
                'enrollment_id' => $enrollment->value,
                'subject_id' => $subject,
                'statement_id' => $statement,
                'satisfied_at' => $at,
            ], ['satisfied_at' => Types::DATETIME_UTC_IMMUTABLE]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function releaseSatisfaction(EnrollmentId $enrollment, string $subject): void
    {
        $this->connection->delete(CourseTables::SATISFACTION, ['enrollment_id' => $enrollment->value, 'subject_id' => $subject]);
    }

    #[Override]
    public function saveProgress(Progress $progress): void
    {
        $this->rows->put(CourseTables::PROGRESS, ['enrollment_id' => $progress->enrollment->value, 'activity_id' => $progress->activity->value], [
            'completed' => $progress->completed,
            'success' => $progress->success,
            'waived' => $progress->waived,
            'score' => $progress->score?->scaled,
            'failures' => $progress->failures,
            'extra_tries' => $progress->extraTries,
            'started_at' => $progress->startedAt,
            'satisfied_at' => $progress->satisfiedAt,
            'updated_at' => $progress->updatedAt,
        ], [
            'completed' => Types::BOOLEAN,
            'success' => Types::BOOLEAN,
            'waived' => Types::BOOLEAN,
            'started_at' => Types::DATETIME_UTC_IMMUTABLE,
            'satisfied_at' => Types::DATETIME_UTC_IMMUTABLE,
            'updated_at' => Types::DATETIME_UTC_IMMUTABLE,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function enrollment(array $row): Enrollment
    {
        return new Enrollment(
            EnrollmentId::of(Rows::text($row['id'])),
            CourseId::of(Rows::text($row['course_id'])),
            LearnerId::of(Rows::text($row['learner'])),
            $this->rows->requiredMoment($row['enrolled_at']),
            Rows::text($row['registration']),
            $this->rows->moment($row['completed_at']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function progressOf(array $row): Progress
    {
        $score = Rows::nullableFloat($row['score']);

        return new Progress(
            EnrollmentId::of(Rows::text($row['enrollment_id'])),
            ActivityId::of(Rows::text($row['activity_id'])),
            $this->rows->requiredMoment($row['updated_at']),
            Rows::bool($row['completed']),
            Rows::nullableBool($row['success']),
            Rows::bool($row['waived']),
            null === $score ? null : Score::scaled(max(-1.0, min(1.0, $score))),
            Rows::int($row['failures']),
            Rows::int($row['extra_tries']),
            $this->rows->moment($row['started_at']),
            $this->rows->moment($row['satisfied_at']),
        );
    }
}
