<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Path;

use LogicException;
use Phil\CoursesBundle\Application\Path\LearningPaths;
use Phil\CoursesBundle\Application\Path\PathCompletions;
use Phil\CoursesBundle\Application\Path\PathProgress;
use Phil\CoursesBundle\Application\Path\StepStatus;
use Phil\CoursesBundle\Domain\Event\CourseCompleted;
use Phil\CoursesBundle\Domain\Event\LearnerEnrolledInPath;
use Phil\CoursesBundle\Domain\Event\PathCompleted;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LearningPath;
use Phil\CoursesBundle\Domain\Model\PathEnrollment;
use Phil\CoursesBundle\Domain\Model\PathStep;
use Phil\CoursesBundle\Domain\Path\StepState;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\LearningPathId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\PathEnrollmentId;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLearningPathRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The learning paths over SQLite: the steps and their prerequisites, the enrollment in the path that enrolls in the
 * courses opened, and the path completed — by the courses or by equivalent courses.
 */
#[CoversClass(LearningPaths::class)]
#[CoversClass(PathCompletions::class)]
#[CoversClass(PathProgress::class)]
#[CoversClass(StepStatus::class)]
#[CoversClass(LearningPath::class)]
#[CoversClass(PathStep::class)]
#[CoversClass(PathEnrollment::class)]
#[CoversClass(LearningPathId::class)]
#[CoversClass(PathEnrollmentId::class)]
#[CoversClass(LearnerEnrolledInPath::class)]
#[CoversClass(PathCompleted::class)]
#[CoversClass(DbalLearningPathRepository::class)]
#[CoversClass(DbalCourseRepository::class)]
final class LearningPathsTest extends TestCase
{
    private Store $store;

    /** @var array<string, array{Course, Activity}> */
    private array $courses = [];

    protected function setUp(): void
    {
        $this->store = new Store();
        $this->store->events->listeners[] = fn (object $event): mixed => $event instanceof CourseCompleted ? ($this->store->pathCompletions)($event) : null;

        foreach (['basics', 'tools', 'safety', 'project', 'extra'] as $name) {
            $course = $this->store->courses->create(ucfirst($name));
            $module = $this->store->courses->addModule($course->id, 'Module');
            $this->courses[$name] = [$course, $this->store->courses->addActivity($module->id, 'Read', 'page')];
        }
    }

    public function testAPathIsCreatedRenamedListedAndRemoved(): void
    {
        $path = $this->store->paths->create('  Welding  ', '  From the first bead to the project.  ');
        self::assertSame(['Welding', 'From the first bead to the project.', '2026-11-13 10:00'], [$path->title, $path->description, $path->createdAt->format('Y-m-d H:i')]);
        self::assertEquals($path, $this->store->paths->get($path->id));
        $this->store->clock->modify('+1 minute');
        $second = $this->store->paths->create('Painting');

        self::assertSame(['Painting', 'Welding'], array_map(static fn (LearningPath $found): string => $found->title, $this->store->paths->all()));
        self::assertSame(['Welding'], array_map(static fn (LearningPath $found): string => $found->title, $this->store->paths->all(1, 1)));
        $renamed = $this->store->paths->rename($path->id, 'Arc welding', 'Arcs.');
        self::assertSame(['Arc welding', 'Arcs.'], [$renamed->title, $this->store->paths->get($path->id)->description]);

        $this->store->paths->addStep($second->id, $this->id('basics'));
        $this->store->paths->enroll($second->id, LearnerId::of('ada'));
        $this->store->paths->remove($second->id);
        self::assertSame([], $this->store->paths->steps($second->id));
        self::assertNull($this->store->paths->of($second->id, LearnerId::of('ada')));
        self::assertInstanceOf(Enrollment::class, $this->store->enrollments->of($this->id('basics'), LearnerId::of('ada')));

        try {
            $this->store->paths->rename($second->id, 'Gone', '');
            self::fail('Renamed a removed path.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertSame(\sprintf('No learning path "%s" is known.', $second->id->value), $unknownRecord->getMessage());
        }

        try {
            new LearningPath(LearningPathId::generate(), 'Long', $this->store->clock->now(), str_repeat('é', 10_001));
            self::fail('A long description.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame('A description is 10000 characters at most.', $invalidCourse->getMessage());
        }

        self::assertSame(10_000, mb_strlen(new LearningPath(LearningPathId::generate(), 'Long', $this->store->clock->now(), str_repeat('é', 10_000))->description));
        $this->expectException(InvalidCourse::class);
        $path->rename('Kept', str_repeat('a', 10_001));
    }

    public function testTheStepsKeepTheirOrderAndTheirPrerequisites(): void
    {
        $path = $this->store->paths->create('Welding');
        $basics = $this->store->paths->addStep($path->id, $this->id('basics'));
        self::assertSame([0, true, []], [$basics->position, $basics->required, $basics->prerequisites]);
        $this->store->paths->addStep($path->id, $this->id('project'), true, [$this->id('basics')]);
        $tools = $this->store->paths->addStep($path->id, $this->id('tools'), false, [$this->id('basics')], $this->id('project'));
        self::assertSame(1, $tools->position);
        $this->store->paths->addStep($path->id, $this->id('safety'), true, [], $this->id('basics'));

        self::assertSame([
            ['safety', 0, true, []],
            ['basics', 1, true, []],
            ['tools', 2, false, ['basics']],
            ['project', 3, true, ['basics']],
        ], $this->describe($this->store->paths->steps($path->id)));
        self::assertTrue($this->store->paths->steps($path->id)[3]->waitsFor($this->id('basics')));
        self::assertFalse($this->store->paths->steps($path->id)[3]->waitsFor($this->id('tools')));

        $this->store->paths->removeStep($path->id, $this->id('basics'));
        self::assertSame([
            ['safety', 0, true, []],
            ['tools', 1, false, []],
            ['project', 2, true, []],
        ], $this->describe($this->store->paths->steps($path->id)));
    }

    public function testAWrongStepIsRefused(): void
    {
        $path = $this->store->paths->create('Welding');
        $this->store->paths->addStep($path->id, $this->id('basics'));

        foreach ([
            'The course "%s" is already a step of the path.' => fn (): PathStep => $this->store->paths->addStep($path->id, $this->id('basics')),
            'The prerequisite "%s" is not a step placed before this one.' => fn (): PathStep => $this->store->paths->addStep($path->id, $this->id('tools'), true, [$this->id('basics')], $this->id('basics')),
            'The course "%s" is not a step of the path.' => fn (): PathStep => $this->store->paths->addStep($path->id, $this->id('tools'), true, [], $this->id('safety')),
        ] as $message => $add) {
            try {
                $add();
                self::fail('Added.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertStringStartsWith(explode('%s', $message)[0], $invalidCourse->getMessage());
                self::assertStringEndsWith(explode('%s', $message)[1], $invalidCourse->getMessage());
            }
        }

        try {
            $this->store->paths->addStep($path->id, $this->id('tools'), true, [$this->id('project')]);
            self::fail('A prerequisite outside the path.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame(\sprintf('The prerequisite "%s" is not a step placed before this one.', $this->id('project')->value), $invalidCourse->getMessage());
        }

        try {
            $this->store->paths->addStep($path->id, CourseId::generate());
            self::fail('An unknown course.');
        } catch (UnknownRecord $unknownRecord) {
            self::assertStringContainsString('No course', $unknownRecord->getMessage());
        }

        foreach ([fn (): PathStep => $this->store->paths->addStep(LearningPathId::generate(), $this->id('tools')), fn () => $this->store->paths->removeStep(LearningPathId::generate(), $this->id('tools')), fn (): PathEnrollment => $this->store->paths->enroll(LearningPathId::generate(), LearnerId::of('ada'))] as $call) {
            try {
                $call();
                self::fail('An unknown path.');
            } catch (UnknownRecord $unknownRecord) {
                self::assertStringContainsString('No learning path', $unknownRecord->getMessage());
            }
        }

        foreach ([[$this->id('tools'), $this->id('tools')], [$this->id('basics'), $this->id('basics')]] as $prerequisites) {
            try {
                new PathStep($path->id, $this->id('tools'), 1, true, $prerequisites);
                self::fail('A wrong prerequisite.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('A step of a path waits for other courses, each once.', $invalidCourse->getMessage());
            }
        }

        self::assertCount(50, new PathStep($path->id, $this->id('tools'), 1, true, array_map(CourseId::generate(...), range(1, 50)))->prerequisites);
        $this->expectExceptionObject(InvalidCourse::because('A step of a path waits for 50 courses at most.'));
        new PathStep($path->id, $this->id('tools'), 1, true, array_map(CourseId::generate(...), range(1, 51)));
    }

    public function testAPathHoldsAHundredStepsAtMost(): void
    {
        $path = $this->store->paths->create('Long');
        $steps = [];

        for ($index = 0; $index < LearningPaths::MAX_STEPS - 1; ++$index) {
            $steps[] = new PathStep($path->id, CourseId::generate(), $index);
        }

        $this->store->pathStore->saveSteps($path->id, $steps);
        $this->store->paths->addStep($path->id, $this->id('basics'));
        self::assertCount(100, $this->store->paths->steps($path->id));
        $this->expectExceptionObject(InvalidCourse::because('A path holds 100 steps at most.'));
        $this->store->paths->addStep($path->id, $this->id('tools'));
    }

    public function testEnrollingOpensTheCoursesWhosePrerequisitesAreCompleted(): void
    {
        $path = $this->path();
        $ada = LearnerId::of('ada');
        $enrollment = $this->store->paths->enroll($path->id, $ada);
        self::assertEquals($enrollment, $this->store->paths->enroll($path->id, $ada));
        self::assertEquals($enrollment, $this->store->paths->enrollment($enrollment->id));
        self::assertEquals($enrollment, $this->store->paths->of($path->id, $ada));
        self::assertEquals([$enrollment], $this->store->paths->ofLearner($ada));
        self::assertCount(1, $this->store->events->of(LearnerEnrolledInPath::class));
        self::assertSame([$enrollment->id->value, $path->id->value, 'ada'], [$this->store->events->of(LearnerEnrolledInPath::class)[0]->enrollment, $this->store->events->of(LearnerEnrolledInPath::class)[0]->path, $this->store->events->of(LearnerEnrolledInPath::class)[0]->learner]);

        self::assertSame(['basics' => true, 'tools' => true, 'safety' => false, 'project' => false], $this->enrolled($ada));
        $progress = $this->store->paths->progress($enrollment);
        self::assertSame([StepState::Open, StepState::Open, StepState::Locked, StepState::Locked], array_map(static fn (StepStatus $status): StepState => $status->state, $progress->steps));
        self::assertSame(0, $progress->percent);
        self::assertNull($progress->steps[0]->completion);

        $this->complete('basics', $ada);
        self::assertSame(['basics' => true, 'tools' => true, 'safety' => true, 'project' => false], $this->enrolled($ada));
        $progress = $this->store->paths->progress($enrollment);
        self::assertSame([StepState::Completed, StepState::Open, StepState::Open, StepState::Locked], array_map(static fn (StepStatus $status): StepState => $status->state, $progress->steps));
        self::assertSame(33, $progress->percent);
        self::assertEquals($this->id('basics'), $progress->steps[0]->completion?->course);

        $this->complete('safety', $ada);
        self::assertSame(['basics' => true, 'tools' => true, 'safety' => true, 'project' => true], $this->enrolled($ada));
        self::assertNull($this->store->paths->completion($path->id, $ada));
        self::assertSame([], $this->store->events->of(PathCompleted::class));

        $this->store->clock->modify('+1 day');
        $this->complete('project', $ada);
        $completed = $this->store->paths->completion($path->id, $ada) ?? throw new LogicException('Not completed.');
        self::assertSame('2026-11-14 10:00', $completed->completedAt?->format('Y-m-d H:i'));
        self::assertTrue($completed->isCompleted());
        self::assertSame(100, $this->store->paths->progress($completed)->percent);
        self::assertCount(1, $this->store->events->of(PathCompleted::class));
        self::assertSame([$enrollment->id->value, $path->id->value, 'ada'], [$this->store->events->of(PathCompleted::class)[0]->enrollment, $this->store->events->of(PathCompleted::class)[0]->path, $this->store->events->of(PathCompleted::class)[0]->learner]);

        // The optional step completed later changes nothing, and the completion keeps its first moment.
        $this->store->clock->modify('+1 day');
        $this->complete('tools', $ada);
        self::assertCount(1, $this->store->events->of(PathCompleted::class));
        self::assertFalse($this->store->paths->advance($completed));
        self::assertSame('2026-11-14 10:00', $this->store->paths->enrollment($enrollment->id)->completedAt?->format('Y-m-d H:i'));
        self::assertSame(100, $this->store->paths->progress($completed)->percent);
    }

    public function testCoursesCompletedBeforeTheEnrollmentCount(): void
    {
        $path = $this->path();
        $ada = LearnerId::of('ada');

        foreach (['basics', 'safety', 'project'] as $name) {
            $this->complete($name, $ada);
        }

        self::assertSame([], $this->store->events->of(PathCompleted::class));
        $enrollment = $this->store->paths->enroll($path->id, $ada);
        self::assertTrue($enrollment->isCompleted());
        self::assertCount(1, $this->store->events->of(PathCompleted::class));
        self::assertInstanceOf(Enrollment::class, $this->store->enrollments->of($this->id('tools'), $ada));
    }

    public function testAnEquivalentCourseCompletesItsStep(): void
    {
        $path = $this->store->paths->create('Short');
        $this->store->paths->addStep($path->id, $this->id('basics'));
        $this->store->paths->addStep($path->id, $this->id('project'), true, [$this->id('basics')]);

        $group = $this->store->equivalences->create('Basics');
        $this->store->equivalences->add($group->id, $this->id('basics'), 'en');
        $this->store->equivalences->add($group->id, $this->id('extra'), 'fr');

        $ada = LearnerId::of('ada');
        $enrollment = $this->store->paths->enroll($path->id, $ada);

        $this->complete('extra', $ada);
        self::assertInstanceOf(Enrollment::class, $this->store->enrollments->of($this->id('project'), $ada));
        self::assertEquals($this->id('extra'), $this->store->paths->progress($enrollment)->steps[0]->completion?->course);
        $this->complete('project', $ada);
        self::assertTrue($this->store->paths->enrollment($enrollment->id)->isCompleted());

        // The listener without the groups advances only the paths of the course completed.
        $bob = LearnerId::of('bob');
        $other = $this->store->paths->enroll($path->id, $bob);
        $listener = new PathCompletions($this->store->pathStore, $this->store->paths);
        $this->store->enrollments->record($this->store->enrollments->enroll($this->id('extra'), $bob), $this->courses['extra'][1], Outcome::completed());
        $events = $this->store->events->of(CourseCompleted::class);
        $this->store->events->listeners = [];
        $this->store->enrollments->record($this->store->enrollments->enroll($this->id('project'), $bob), $this->courses['project'][1], Outcome::completed());
        self::assertFalse($this->store->paths->enrollment($other->id)->isCompleted());
        $listener($events[\count($events) - 1]);
        self::assertFalse($this->store->paths->enrollment($other->id)->isCompleted());
        $events = $this->store->events->of(CourseCompleted::class);
        $listener($events[\count($events) - 1]);
        self::assertTrue($this->store->paths->enrollment($other->id)->isCompleted());
    }

    public function testAnEmptyPathIsNeverCompletedAndARemovedCourseHoldsNothingBack(): void
    {
        $empty = $this->store->paths->create('Empty');
        $ada = LearnerId::of('ada');
        $enrollment = $this->store->paths->enroll($empty->id, $ada);
        self::assertFalse($enrollment->isCompleted());
        self::assertSame(0, $this->store->paths->progress($enrollment)->percent);
        $enrollment->complete($this->store->clock->now());
        self::assertSame(100, $this->store->paths->progress($enrollment)->percent);

        $path = $this->store->paths->create('Short');
        $this->store->paths->addStep($path->id, $this->id('basics'));
        $this->store->paths->addStep($path->id, $this->id('project'), true, [$this->id('basics')]);
        $this->store->courseStore->remove($this->id('basics'));
        self::assertSame([['project', 1, true, ['basics']]], $this->describe($this->store->paths->steps($path->id)));
        $this->store->paths->enroll($path->id, $ada);
        self::assertInstanceOf(Enrollment::class, $this->store->enrollments->of($this->id('project'), $ada));
        $this->complete('project', $ada);
        self::assertInstanceOf(PathEnrollment::class, $this->store->paths->completion($path->id, $ada));
    }

    public function testTheEnrollmentsOfALearnerAreFoundAndRemoved(): void
    {
        $path = $this->path();
        $other = $this->store->paths->create('Other');
        $this->store->paths->addStep($other->id, $this->id('extra'));
        $ada = LearnerId::of('ada');
        $first = $this->store->paths->enroll($path->id, $ada);
        $this->store->clock->modify('+1 minute');
        $second = $this->store->paths->enroll($other->id, $ada);
        $this->store->paths->enroll($path->id, LearnerId::of('bob'));

        self::assertEquals([$second, $first], $this->store->paths->ofLearner($ada));
        self::assertEquals([$first], $this->store->pathStore->openEnrollmentsWith($ada, [$this->id('basics'), $this->id('tools')]));
        self::assertEquals([$first, $second], $this->store->pathStore->openEnrollmentsWith($ada, [$this->id('basics'), $this->id('extra')]));
        self::assertSame([], $this->store->pathStore->openEnrollmentsWith($ada, []));

        $this->store->pathStore->removeEnrollmentsOf($ada);
        self::assertSame([], $this->store->paths->ofLearner($ada));
        self::assertCount(1, $this->store->paths->ofLearner(LearnerId::of('bob')));

        $this->expectException(UnknownRecord::class);
        $this->store->paths->enrollment($first->id);
    }

    public function testPrerequisitesThatAreNotAListReadAsNone(): void
    {
        $path = $this->store->paths->create('Short');
        $this->store->paths->addStep($path->id, $this->id('basics'));
        $this->store->paths->addStep($path->id, $this->id('project'), true, [$this->id('basics')]);

        foreach (['{', '"text"', '[["deep"]]'] as $json) {
            $this->store->connection->update('phil_course_path_step', ['prerequisites' => $json], ['course_id' => $this->id('project')->value]);
            self::assertSame([], $this->store->paths->steps($path->id)[1]->prerequisites);
        }
    }

    /**
     * Basics and tools open at once, tools optional; safety after basics; the project after basics and safety.
     */
    private function path(): LearningPath
    {
        $path = $this->store->paths->create('Welding');
        $this->store->paths->addStep($path->id, $this->id('basics'));
        $this->store->paths->addStep($path->id, $this->id('tools'), false);
        $this->store->paths->addStep($path->id, $this->id('safety'), true, [$this->id('basics')]);
        $this->store->paths->addStep($path->id, $this->id('project'), true, [$this->id('basics'), $this->id('safety')]);

        return $path;
    }

    private function complete(string $name, LearnerId $learner): void
    {
        [$course, $page] = $this->courses[$name];
        $this->store->enrollments->record($this->store->enrollments->enroll($course->id, $learner), $page, Outcome::completed());
    }

    /**
     * @return array<string, bool> whether the learner is enrolled in each course of the path
     */
    private function enrolled(LearnerId $learner): array
    {
        $enrolled = [];

        foreach (['basics', 'tools', 'safety', 'project'] as $name) {
            $enrolled[$name] = $this->store->enrollments->of($this->id($name), $learner) instanceof Enrollment;
        }

        return $enrolled;
    }

    private function id(string $name): CourseId
    {
        return isset($this->courses[$name]) ? $this->courses[$name][0]->id : CourseId::of('unknown-'.$name);
    }

    /**
     * @param list<PathStep> $steps
     *
     * @return list<array{string, int, bool, list<string>}>
     */
    private function describe(array $steps): array
    {
        $names = [];

        foreach ($this->courses as $name => [$course]) {
            $names[$course->id->value] = $name;
        }

        return array_map(static fn (PathStep $step): array => [
            $names[$step->course->value] ?? $step->course->value,
            $step->position,
            $step->required,
            array_map(static fn (CourseId $course): string => $names[$course->value] ?? $course->value, $step->prerequisites),
        ], $steps);
    }
}
