<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use JsonException;
use Override;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\BlockId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Release;

/**
 * The courses, their modules, blocks and activities through DBAL. The settings of an activity are kept as JSON.
 */
final readonly class DbalCourseRepository implements CourseRepository
{
    private const array RELEASE_TYPES = ['release_on' => Types::DATETIME_UTC_IMMUTABLE];

    private Rows $rows;

    public function __construct(
        private Connection $connection,
    ) {
        $this->rows = new Rows($connection);
    }

    #[Override]
    public function save(Course $course): void
    {
        $this->rows->put(CourseTables::COURSE, ['id' => $course->id->value], [
            'title' => $course->title,
            'description' => $course->description,
            'origin' => $course->origin->value,
            'source' => $course->source,
            'ordered' => $course->ordered,
            'created_at' => $course->createdAt,
        ], ['ordered' => Types::BOOLEAN, 'created_at' => Types::DATETIME_UTC_IMMUTABLE]);
    }

    #[Override]
    public function saveModule(Module $module): void
    {
        $this->rows->put(CourseTables::MODULE, ['id' => $module->id->value], [
            'course_id' => $module->course->value,
            'title' => $module->title,
            'position' => $module->position,
            'release_on' => $module->release->on,
            'release_days' => $module->release->days,
        ], self::RELEASE_TYPES);
    }

    #[Override]
    public function saveActivity(Activity $activity): void
    {
        $this->rows->put(CourseTables::ACTIVITY, ['id' => $activity->id->value], [
            'course_id' => $activity->course->value,
            'module_id' => $activity->module->value,
            'title' => $activity->title,
            'type' => $activity->type,
            'position' => $activity->position,
            'settings' => json_encode($activity->settings, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION),
            'requirement' => $activity->requirement->value,
            'required' => $activity->required,
            'max_attempts' => $activity->maxAttempts,
            'release_on' => $activity->release->on,
            'release_days' => $activity->release->days,
            'source' => $activity->source,
            'block_id' => $activity->block?->value,
        ], ['required' => Types::BOOLEAN] + self::RELEASE_TYPES);
    }

    #[Override]
    public function saveBlock(Block $block): void
    {
        $this->rows->put(CourseTables::BLOCK, ['id' => $block->id->value], [
            'course_id' => $block->course->value,
            'parent_id' => $block->parent?->value,
            'title' => $block->title,
            'position' => $block->position,
            'source' => $block->source,
        ]);
    }

    #[Override]
    public function find(CourseId $id): ?Course
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::COURSE.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->course($row);
    }

    #[Override]
    public function module(ModuleId $id): ?Module
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::MODULE.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->moduleOf($row);
    }

    #[Override]
    public function activity(ActivityId $id): ?Activity
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.CourseTables::ACTIVITY.' WHERE id = ?', [$id->value]);

        return false === $row ? null : $this->activityOf($row);
    }

    #[Override]
    public function modules(CourseId $course): array
    {
        return array_map($this->moduleOf(...), $this->connection->fetchAllAssociative('SELECT * FROM '.CourseTables::MODULE.' WHERE course_id = ? ORDER BY position, id', [$course->value]));
    }

    #[Override]
    public function activities(CourseId $course): array
    {
        return array_map($this->activityOf(...), $this->connection->fetchAllAssociative(
            'SELECT a.* FROM '.CourseTables::ACTIVITY.' a JOIN '.CourseTables::MODULE.' m ON m.id = a.module_id WHERE a.course_id = ? ORDER BY m.position, a.position, a.id',
            [$course->value],
        ));
    }

    #[Override]
    public function blocks(CourseId $course): array
    {
        $children = [];

        foreach ($this->connection->fetchAllAssociative('SELECT * FROM '.CourseTables::BLOCK.' WHERE course_id = ? ORDER BY position, id', [$course->value]) as $row) {
            $block = $this->blockOf($row);
            $children[$block->parent->value ?? ''][] = $block;
        }

        return $this->depthFirst($children, '');
    }

    #[Override]
    public function all(int $limit = 50, int $offset = 0): array
    {
        return array_map($this->course(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM '.CourseTables::COURSE.' ORDER BY created_at DESC, id DESC LIMIT '.max(1, $limit).' OFFSET '.max(0, $offset),
        ));
    }

    #[Override]
    public function remove(CourseId $id): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($id): void {
            $enrollments = 'SELECT id FROM '.CourseTables::ENROLLMENT.' WHERE course_id = ?';

            foreach ([CourseTables::PROGRESS, CourseTables::SATISFACTION, CourseTables::RETRY, CourseTables::SESSION] as $table) {
                $connection->executeStatement('DELETE FROM '.$table.' WHERE enrollment_id IN ('.$enrollments.')', [$id->value]);
            }

            $connection->executeStatement('DELETE FROM '.CourseTables::ENROLLMENT.' WHERE course_id = ?', [$id->value]);
            $connection->executeStatement('DELETE FROM '.CourseTables::ACTIVITY.' WHERE course_id = ?', [$id->value]);
            $connection->executeStatement('DELETE FROM '.CourseTables::BLOCK.' WHERE course_id = ?', [$id->value]);
            $connection->executeStatement('DELETE FROM '.CourseTables::MODULE.' WHERE course_id = ?', [$id->value]);
            $connection->executeStatement('DELETE FROM '.CourseTables::COURSE.' WHERE id = ?', [$id->value]);
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function course(array $row): Course
    {
        return new Course(
            CourseId::of(Rows::text($row['id'])),
            Rows::text($row['title']),
            $this->rows->requiredMoment($row['created_at']),
            Rows::text($row['description']),
            CourseOrigin::tryFrom(Rows::text($row['origin'])) ?? CourseOrigin::Native,
            Rows::nullableText($row['source']),
            Rows::bool($row['ordered']),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function moduleOf(array $row): Module
    {
        return new Module(
            ModuleId::of(Rows::text($row['id'])),
            CourseId::of(Rows::text($row['course_id'])),
            Rows::text($row['title']),
            max(0, Rows::int($row['position'])),
            $this->release($row),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function activityOf(array $row): Activity
    {
        return new Activity(
            ActivityId::of(Rows::text($row['id'])),
            CourseId::of(Rows::text($row['course_id'])),
            ModuleId::of(Rows::text($row['module_id'])),
            Rows::text($row['title']),
            Rows::text($row['type']),
            max(0, Rows::int($row['position'])),
            $this->settings(Rows::text($row['settings'])),
            CompletionRequirement::tryFrom(Rows::text($row['requirement'])) ?? CompletionRequirement::Completed,
            Rows::bool($row['required']),
            Rows::nullableInt($row['max_attempts']),
            $this->release($row),
            Rows::nullableText($row['source']),
            null === ($block = Rows::nullableText($row['block_id'])) ? null : BlockId::of($block),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function blockOf(array $row): Block
    {
        $parent = Rows::nullableText($row['parent_id']);

        return new Block(
            BlockId::of(Rows::text($row['id'])),
            CourseId::of(Rows::text($row['course_id'])),
            Rows::text($row['title']),
            max(0, Rows::int($row['position'])),
            Rows::text($row['source']),
            null === $parent ? null : BlockId::of($parent),
        );
    }

    /**
     * The blocks under a parent, each followed by the blocks it holds: a block whose parent is gone is left out.
     *
     * @param array<string, list<Block>> $children by the identifier of their parent, '' for the first level
     *
     * @return list<Block>
     */
    private function depthFirst(array $children, string $parent): array
    {
        $blocks = [];

        foreach ($children[$parent] ?? [] as $block) {
            $blocks = [...$blocks, $block, ...$this->depthFirst($children, $block->id->value)];
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function release(array $row): Release
    {
        $on = $this->rows->moment($row['release_on']);
        $days = Rows::nullableInt($row['release_days']);

        return match (true) {
            $on instanceof DateTimeImmutable => Release::on($on),
            null !== $days => Release::daysAfterEnrollment($days),
            default => Release::immediately(),
        };
    }

    /**
     * @return array<array-key, mixed>
     */
    private function settings(string $json): array
    {
        try {
            // The settings are named scalars: one object, its values one level below.
            $settings = json_decode($json, true, 2, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return \is_array($settings) ? $settings : [];
    }
}
