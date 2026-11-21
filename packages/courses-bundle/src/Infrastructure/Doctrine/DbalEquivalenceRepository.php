<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\CoursesBundle\Domain\Contract\EquivalenceRepository;
use Phil\CoursesBundle\Domain\Model\EquivalenceGroup;
use Phil\CoursesBundle\Domain\Model\EquivalentCourse;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EquivalenceGroupId;

/**
 * The groups of equivalent courses through DBAL.
 */
final readonly class DbalEquivalenceRepository implements EquivalenceRepository
{
    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(EquivalenceGroup $group): void
    {
        $this->rows->put(CourseTables::EQUIVALENCE, ['id' => $group->id->value], [
            'title' => $group->title,
            'created_at' => $group->createdAt,
        ], ['created_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function find(EquivalenceGroupId $id): ?EquivalenceGroup
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::EQUIVALENCE.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->group($row);
    }

    #[Override]
    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_map($this->group(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::EQUIVALENCE.' ORDER BY title, id LIMIT '.max(1, $limit).' OFFSET '.max(0, $offset),
        ));
    }

    #[Override]
    public function remove(EquivalenceGroupId $id): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($id): void {
            $connection->delete(CourseTables::EQUIVALENT, ['group_id' => $id->value]);
            $connection->delete(CourseTables::EQUIVALENCE, ['id' => $id->value]);
        });
    }

    #[Override]
    public function saveCourse(EquivalentCourse $course): void
    {
        $this->rows->put(CourseTables::EQUIVALENT, ['course_id' => $course->course->value], [
            'group_id' => $course->group->value,
            'language' => $course->language,
        ]);
    }

    #[Override]
    public function removeCourse(CourseId $course): void
    {
        $this->connection->delete(CourseTables::EQUIVALENT, ['course_id' => $course->value]);
    }

    #[Override]
    public function membership(CourseId $course): ?EquivalentCourse
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::EQUIVALENT.' WHERE course_id = ?', [$course->value]);

        return false === $row ? null : $this->member($row);
    }

    #[Override]
    public function courses(EquivalenceGroupId $group): array
    {
        return array_map($this->member(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::EQUIVALENT.' WHERE group_id = ? ORDER BY language, course_id',
            [$group->value],
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function group(array $row): EquivalenceGroup
    {
        return new EquivalenceGroup(EquivalenceGroupId::of(Rows::text($row['id'])), Rows::text($row['title']), $this->rows->requiredMoment($row['created_at']));
    }

    /**
     * @param array<string, mixed> $row
     */
    private function member(array $row): EquivalentCourse
    {
        return new EquivalentCourse(EquivalenceGroupId::of(Rows::text($row['group_id'])), CourseId::of(Rows::text($row['course_id'])), Rows::text($row['language']));
    }
}
