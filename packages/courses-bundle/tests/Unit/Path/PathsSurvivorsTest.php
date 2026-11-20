<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Path;

use Phil\CoursesBundle\Application\Path\LearningPaths;
use Phil\CoursesBundle\Domain\Event\PathCompleted;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\LearningPath;
use Phil\CoursesBundle\Domain\Model\PathStep;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningPathRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The bounds and the defaults of the learning paths that the other tests do not reach.
 */
#[CoversClass(LearningPaths::class)]
#[CoversClass(PathStep::class)]
#[CoversClass(DbalLearningPathRepository::class)]
final class PathsSurvivorsTest extends TestCase
{
    public function testAPageOfPathsHoldsFiftyAtMostTheLatestFirst(): void
    {
        $store = new Store();
        $ids = [];

        for ($number = 0; $number < 51; ++$number) {
            $ids[] = $store->paths->create('Path '.$number)->id->value;
            $store->clock->modify('+1 minute');
        }

        self::assertCount(50, $store->paths->all());
        self::assertCount(50, $store->pathStore->all());
        self::assertSame($ids[50], $store->pathStore->all()[0]->id->value);
        self::assertSame($ids[50], $store->paths->all()[0]->id->value);
        self::assertCount(1, $store->pathStore->all(1));
        self::assertCount(2, $store->pathStore->all(2));
    }

    public function testRemovingAPathLeavesTheOtherPathsAlone(): void
    {
        $store = new Store();
        [$course] = $this->course($store, 'Basics');
        $kept = $store->paths->create('Kept');
        $removed = $store->paths->create('Removed');

        foreach ([$kept, $removed] as $path) {
            $store->paths->addStep($path->id, $course->id);
            $store->paths->enroll($path->id, LearnerId::of('ada'));
        }

        $store->paths->remove($removed->id);

        self::assertCount(1, $store->paths->steps($kept->id));
        self::assertNotNull($store->paths->of($kept->id, LearnerId::of('ada')));
        self::assertNotNull($store->paths->get($kept->id));
        self::assertSame([], $store->paths->steps($removed->id));
        self::assertNull($store->paths->of($removed->id, LearnerId::of('ada')));
    }

    public function testTheStepsAreReplacedAndTheirFlagsAreKept(): void
    {
        $store = new Store();
        [$basics] = $this->course($store, 'Basics');
        [$tools] = $this->course($store, 'Tools');
        $first = $store->paths->create('First');
        $second = $store->paths->create('Second');
        $store->paths->addStep($first->id, $basics->id, false);
        $store->paths->addStep($second->id, $tools->id, true);
        $store->paths->addStep($first->id, $tools->id, true);

        self::assertSame([false, true], array_map(static fn (PathStep $step): bool => $step->required, $store->paths->steps($first->id)));
        self::assertSame([true], array_map(static fn (PathStep $step): bool => $step->required, $store->paths->steps($second->id)));

        $store->connection->update('phil_course_path_step', ['position' => 5], ['course_id' => $tools->id->value, 'path_id' => $first->id->value]);
        self::assertSame([0, 5], array_map(static fn (PathStep $step): int => $step->position, $store->paths->steps($first->id)));
    }

    public function testPrerequisitesStoredAsAnObjectAreReadAsAList(): void
    {
        $store = new Store();
        [$basics] = $this->course($store, 'Basics');
        [$project] = $this->course($store, 'Project');
        $path = $store->paths->create('Welding');
        $store->paths->addStep($path->id, $basics->id);
        $store->paths->addStep($path->id, $project->id, true, [$basics->id]);
        $store->connection->update('phil_course_path_step', ['prerequisites' => '{"7":"'.$basics->id->value.'"}'], ['course_id' => $project->id->value]);

        $prerequisites = $store->paths->steps($path->id)[1]->prerequisites;

        self::assertSame([0], array_keys($prerequisites));
        self::assertEquals([$basics->id], $prerequisites);
    }

    public function testEnrollmentsOfNoCourseAreNone(): void
    {
        $store = new Store();
        [$basics] = $this->course($store, 'Basics');
        $path = $store->paths->create('Welding');
        $store->paths->addStep($path->id, $basics->id);
        $store->paths->enroll($path->id, LearnerId::of('ada'));

        self::assertSame([], $store->pathStore->openEnrollmentsWith(LearnerId::of('ada'), []));
        self::assertCount(1, $store->pathStore->openEnrollmentsWith(LearnerId::of('ada'), [$basics->id]));
    }

    public function testAStepIsRequiredUnlessItSaysOtherwise(): void
    {
        self::assertTrue(new PathStep(LearningPathId::generate(), CourseId::of('basics'), 0)->required);
        self::assertFalse(new PathStep(LearningPathId::generate(), CourseId::of('basics'), 0, false)->required);
    }

    public function testTheProgressCountsTheRequiredStepsCompleted(): void
    {
        $store = new Store();
        [$path, $pages] = $this->requiredPath($store, 3);
        $ada = LearnerId::of('ada');
        $enrollment = $store->paths->enroll($path->id, $ada);

        self::assertSame(0, $store->paths->progress($enrollment)->percent);
        $this->complete($store, $pages[0], $ada);
        $this->complete($store, $pages[1], $ada);

        self::assertSame(66, $store->paths->progress($enrollment)->percent);
    }

    public function testAPathOfOptionalStepsIsCompletedAtOnce(): void
    {
        $store = new Store();
        [$basics] = $this->course($store, 'Basics');
        $path = $store->paths->create('Welding');
        $store->paths->addStep($path->id, $basics->id, false);
        $enrollment = $store->paths->enroll($path->id, LearnerId::of('ada'));

        self::assertTrue($enrollment->isCompleted());
        self::assertSame(100, $store->paths->progress($enrollment)->percent);
    }

    public function testAdvancingTellsWhetherThePathWasCompleted(): void
    {
        $store = new Store();
        [$path, $pages] = $this->requiredPath($store, 1);
        $ada = LearnerId::of('ada');
        $enrollment = $store->paths->enroll($path->id, $ada);

        self::assertFalse($store->paths->advance($enrollment));
        $this->complete($store, $pages[0], $ada);

        self::assertTrue($store->paths->advance($enrollment));
        self::assertFalse($store->paths->advance($enrollment));
    }

    public function testThePathsWorkWithoutADispatcher(): void
    {
        $store = new Store();
        $paths = new LearningPaths($store->pathStore, $store->courseStore, $store->enrollments, $store->courseCompletions, $store->clock);
        [$course, $page] = $this->course($store, 'Basics');
        $path = $paths->create('Welding');
        $paths->addStep($path->id, $course->id);
        $ada = LearnerId::of('ada');
        $enrollment = $paths->enroll($path->id, $ada);
        $this->complete($store, $page, $ada);

        self::assertTrue($paths->advance($enrollment));
        self::assertSame([], $store->events->of(PathCompleted::class));
    }

    /**
     * @return array{LearningPath, list<Activity>}
     */
    private function requiredPath(Store $store, int $count): array
    {
        $path = $store->paths->create('Welding');
        $pages = [];

        for ($number = 0; $number < $count; ++$number) {
            [$course, $page] = $this->course($store, 'Course '.$number);
            $store->paths->addStep($path->id, $course->id);
            $pages[] = $page;
        }

        return [$path, $pages];
    }

    private function complete(Store $store, Activity $page, LearnerId $learner): void
    {
        $store->enrollments->record($store->enrollments->enroll($page->course, $learner), $page, Outcome::completed());
    }

    /**
     * @return array{Course, Activity}
     */
    private function course(Store $store, string $title): array
    {
        $course = $store->courses->create($title);
        $module = $store->courses->addModule($course->id, 'Module');

        return [$course, $store->courses->addActivity($module->id, 'Read', 'page')];
    }
}
