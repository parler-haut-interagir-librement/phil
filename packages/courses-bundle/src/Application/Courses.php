<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application;

use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
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
use Psr\Clock\ClockInterface;

/**
 * Writes and reads the courses: a course, its modules in order, their activities in order, each activity checked by
 * its type.
 *
 * @phpstan-import-type Settings from Activity
 */
final readonly class Courses
{
    public const int MAX_MODULES = 500;

    public const int MAX_ACTIVITIES = 500;

    public function __construct(
        private CourseRepository $courses,
        private ActivityTypes $types,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws InvalidCourse when a value is not one a course holds
     */
    public function create(string $title, string $description = '', bool $ordered = false, CourseOrigin $origin = CourseOrigin::Native, ?string $source = null): Course
    {
        $course = new Course(CourseId::generate(), $title, $this->clock->now(), $description, $origin, $source, $ordered);
        $this->courses->save($course);

        return $course;
    }

    /**
     * Adds a module after the course's last one.
     *
     * @throws UnknownRecord when the course is not known
     * @throws InvalidCourse when the title is not one a module holds, or the course has too many modules
     */
    public function addModule(CourseId $course, string $title, ?Release $release = null): Module
    {
        $this->get($course);
        $position = \count($this->courses->modules($course));

        if ($position >= self::MAX_MODULES) {
            throw InvalidCourse::because(\sprintf('A course holds %d modules at most.', self::MAX_MODULES));
        }

        $module = new Module(ModuleId::generate(), $course, $title, $position, $release);
        $this->courses->saveModule($module);

        return $module;
    }

    /**
     * Adds an activity after the module's last one, its settings checked by its type.
     *
     * @param Settings $settings
     *
     * @throws UnknownRecord when the module is not known
     * @throws InvalidCourse when the type is unknown, a setting is wrong, or the module has too many activities
     */
    public function addActivity(
        ModuleId $module,
        string $title,
        string $type,
        array $settings = [],
        CompletionRequirement $requirement = CompletionRequirement::Completed,
        bool $required = true,
        ?int $maxAttempts = null,
        ?Release $release = null,
        ?string $source = null,
        ?BlockId $block = null,
    ): Activity {
        $owner = $this->courses->module($module) ?? throw UnknownRecord::of('module', $module->value);
        $position = \count(array_filter($this->courses->activities($owner->course), static fn (Activity $activity): bool => $activity->module->equals($module)));

        if ($position >= self::MAX_ACTIVITIES) {
            throw InvalidCourse::because(\sprintf('A module holds %d activities at most.', self::MAX_ACTIVITIES));
        }

        $activity = new Activity(ActivityId::generate(), $owner->course, $module, $title, $type, $position, $this->types->get($type)->settings($settings), $requirement, $required, $maxAttempts, $release, $source, $block);
        $this->courses->saveActivity($activity);

        return $activity;
    }

    /**
     * Adds a block of a cmi5 course structure, at its place among the children of its parent. The import of a package
     * bounds how many blocks a course holds.
     *
     * @param int<0, max> $position among the children of the parent, blocks and units counted together
     * @param string      $source   the identifier the course structure gives the block
     *
     * @throws UnknownRecord when the course is not known
     * @throws InvalidCourse when the title or the source is not one a block holds
     */
    public function addBlock(CourseId $course, string $title, string $source, int $position, ?BlockId $parent = null): Block
    {
        $this->get($course);
        $block = new Block(BlockId::generate(), $course, $title, $position, $source, $parent);
        $this->courses->saveBlock($block);

        return $block;
    }

    /**
     * @return list<Block> the blocks of a cmi5 course, each parent before the blocks it holds
     */
    public function blocks(CourseId $course): array
    {
        return $this->courses->blocks($course);
    }

    /**
     * @throws UnknownRecord when the course is not known
     */
    public function get(CourseId $id): Course
    {
        return $this->courses->find($id) ?? throw UnknownRecord::of('course', $id->value);
    }

    /**
     * @throws UnknownRecord when the activity is not known
     */
    public function activity(ActivityId $id): Activity
    {
        return $this->courses->activity($id) ?? throw UnknownRecord::of('activity', $id->value);
    }

    /**
     * @throws UnknownRecord when the course is not known
     */
    public function outline(CourseId $id): CourseOutline
    {
        return CourseOutline::of($this->get($id), $this->courses->modules($id), $this->courses->activities($id));
    }

    /**
     * @return list<Course>
     */
    public function all(int $limit = 50, int $offset = 0): array
    {
        return $this->courses->all(max(1, min($limit, 200)), max(0, $offset));
    }

    public function remove(CourseId $id): void
    {
        $this->courses->remove($id);
    }
}
