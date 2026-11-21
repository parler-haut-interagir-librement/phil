<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Override;
use Phil\CoursesBundle\Domain\Contract\LearningPathRepository;
use Phil\CoursesBundle\Domain\Model\LearningPath;
use Phil\CoursesBundle\Domain\Model\PathEnrollment;
use Phil\CoursesBundle\Domain\Model\PathStep;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;
use Phil\CoursesBundle\Domain\ValueObject\PathEnrollmentId;

/**
 * The learning paths, their steps — the prerequisites of each kept as a JSON list of course identifiers — and the
 * learners enrolled in them through DBAL.
 */
final readonly class DbalLearningPathRepository implements LearningPathRepository
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(LearningPath $path): void
    {
        $this->rows->put(CourseTables::PATH, ['id' => $path->id->value], [
            'title' => $path->title,
            'description' => $path->description,
            'created_at' => $path->createdAt,
        ], ['created_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function find(LearningPathId $id): ?LearningPath
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::PATH.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->path($row);
    }

    #[Override]
    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_map($this->path(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::PATH.' ORDER BY created_at DESC, id DESC LIMIT '.max(1, $limit).' OFFSET '.max(0, $offset),
        ));
    }

    #[Override]
    public function remove(LearningPathId $id): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($id): void {
            $connection->delete(CourseTables::PATH_ENROLLMENT, ['path_id' => $id->value]);
            $connection->delete(CourseTables::PATH_STEP, ['path_id' => $id->value]);
            $connection->delete(CourseTables::PATH, ['id' => $id->value]);
        });
    }

    #[Override]
    public function saveSteps(LearningPathId $path, array $steps): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($path, $steps): void {
            $connection->delete(CourseTables::PATH_STEP, ['path_id' => $path->value]);

            foreach ($steps as $step) {
                $connection->insert(CourseTables::PATH_STEP, [
                    'path_id' => $path->value,
                    'course_id' => $step->course->value,
                    'position' => $step->position,
                    'required' => $step->required,
                    'prerequisites' => json_encode(array_map(static fn (CourseId $course): string => $course->value, $step->prerequisites), \JSON_THROW_ON_ERROR),
                ], ['required' => Types::BOOLEAN]);
            }
        });
    }

    #[Override]
    public function steps(LearningPathId $path): array
    {
        return array_map(static fn (array $row): PathStep => new PathStep(
            $path,
            CourseId::of(Rows::text($row['course_id'])),
            max(0, Rows::int($row['position'])),
            Rows::bool($row['required']),
            self::prerequisites($row['prerequisites']),
        ), $this->connection->fetchAllAssociative(
            'SELECT course_id, position, required, prerequisites FROM '.CourseTables::PATH_STEP.' WHERE path_id = ? ORDER BY position, course_id',
            [$path->value],
        ));
    }

    #[Override]
    public function saveEnrollment(PathEnrollment $enrollment): void
    {
        $this->rows->put(CourseTables::PATH_ENROLLMENT, ['id' => $enrollment->id->value], [
            'path_id' => $enrollment->path->value,
            'learner' => $enrollment->learner->value,
            'enrolled_at' => $enrollment->enrolledAt,
            'completed_at' => $enrollment->completedAt,
        ], ['enrolled_at' => Types::DATETIME_UTC_IMMUTABLE, 'completed_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function enrollment(PathEnrollmentId $id): ?PathEnrollment
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::PATH_ENROLLMENT.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->enrollmentOf($row);
    }

    #[Override]
    public function enrollmentFor(LearningPathId $path, LearnerId $learner): ?PathEnrollment
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::PATH_ENROLLMENT.' WHERE path_id = ? AND learner = ?', [$path->value, $learner->value]);

        return false === $row ? null : $this->enrollmentOf($row);
    }

    #[Override]
    public function enrollmentsOf(LearnerId $learner): array
    {
        return array_map($this->enrollmentOf(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::PATH_ENROLLMENT.' WHERE learner = ? ORDER BY enrolled_at DESC, id DESC',
            [$learner->value],
        ));
    }

    #[Override]
    public function openEnrollmentsWith(LearnerId $learner, array $courses): array
    {
        if ([] === $courses) {
            return [];
        }

        return array_map($this->enrollmentOf(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::PATH_ENROLLMENT.' WHERE learner = ? AND completed_at IS NULL AND path_id IN (SELECT path_id FROM '.CourseTables::PATH_STEP.' WHERE course_id IN (?)) ORDER BY enrolled_at, id',
            [$learner->value, array_map(static fn (CourseId $course): string => $course->value, $courses)],
            [1 => ArrayParameterType::STRING],
        ));
    }

    #[Override]
    public function removeEnrollmentsOf(LearnerId $learner): void
    {
        $this->connection->delete(CourseTables::PATH_ENROLLMENT, ['learner' => $learner->value]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function path(array $row): LearningPath
    {
        return new LearningPath(LearningPathId::of(Rows::text($row['id'])), Rows::text($row['title']), $this->rows->requiredMoment($row['created_at']), Rows::text($row['description']));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function enrollmentOf(array $row): PathEnrollment
    {
        return new PathEnrollment(
            PathEnrollmentId::of(Rows::text($row['id'])),
            LearningPathId::of(Rows::text($row['path_id'])),
            LearnerId::of(Rows::text($row['learner'])),
            $this->rows->requiredMoment($row['enrolled_at']),
            $this->rows->moment($row['completed_at']),
        );
    }

    /**
     * The prerequisites kept as a JSON list; what is not a list of identifiers reads as none.
     *
     * @return list<CourseId>
     */
    private static function prerequisites(mixed $json): array
    {
        try {
            $values = json_decode(Rows::text($json), true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return \is_array($values) ? array_values(array_map(static fn (mixed $value): CourseId => CourseId::of(Rows::text($value)), $values)) : [];
    }
}
