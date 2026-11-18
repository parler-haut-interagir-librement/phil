<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CoursesBundle\Application\ActivityType\PageActivity;
use Phil\CoursesBundle\Application\ActivityTypes;
use Phil\CoursesBundle\Application\Availabilities;
use Phil\CoursesBundle\Application\Contract\ActivityType;
use Phil\CoursesBundle\Application\CourseOutline;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\Enrollments;
use Phil\CoursesBundle\Application\Launch;
use Phil\CoursesBundle\Application\Learning;
use Phil\CoursesBundle\Application\RetryReview;
use Phil\CoursesBundle\Application\SecondChances;
use Phil\CoursesBundle\Domain\Contract\CourseRepository;
use Phil\CoursesBundle\Domain\Contract\RetryRequestRepository;
use Phil\CoursesBundle\Domain\Event\ActivitySatisfied;
use Phil\CoursesBundle\Domain\Exception\ActivityUnavailable;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Block;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\Progress\AvailabilityStatus;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Release;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The defaults and bounds of the entry points, their work without an event dispatcher, and the details of progress
 * and availability the scenarios of the other tests do not reach.
 */
#[CoversClass(Courses::class)]
#[CoversClass(SecondChances::class)]
#[CoversClass(Enrollments::class)]
#[CoversClass(Learning::class)]
#[CoversClass(CourseOutline::class)]
#[CoversClass(Availabilities::class)]
#[CoversClass(ActivityTypes::class)]
final class ApplicationSurvivorsTest extends TestCase
{
    public function testTheCoursesArePagedWithinBounds(): void
    {
        $store = new Store();
        $recording = $this->recordingCourses($store->courseStore);
        $courses = new Courses($recording, $store->types, $store->clock);

        $courses->all();
        $courses->all(0, -5);
        $courses->all(500, 3);
        $courses->all(200, 0);

        self::assertSame([[50, 0], [1, 0], [200, 3], [200, 0]], $recording->calls);
    }

    public function testACourseIsCreatedFreeAndItsActivitiesRequired(): void
    {
        $store = new Store();
        $course = $store->courses->create('Free');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'page');

        self::assertFalse($course->ordered);
        self::assertTrue($activity->required);
    }

    public function testTheWaitingRequestsAreBounded(): void
    {
        $store = new Store();
        $requests = new class implements RetryRequestRepository {
            /** @var list<int> */
            public array $limits = [];

            #[Override]
            public function save(RetryRequest $request): void
            {
            }

            #[Override]
            public function find(RetryRequestId $id): ?RetryRequest
            {
                return null;
            }

            #[Override]
            public function pending(EnrollmentId $enrollment, ActivityId $activity): ?RetryRequest
            {
                return null;
            }

            #[Override]
            public function waiting(int $limit = 50): array
            {
                $this->limits[] = $limit;

                return [];
            }
        };
        $chances = new SecondChances($requests, $store->courses, $store->enrollments, $store->availabilities, $store->clock);

        $chances->waiting();
        $chances->waiting(0);
        $chances->waiting(500);
        $chances->waiting(200);
        $chances->review();

        self::assertSame([50, 1, 200, 200, 50], $requests->limits);
    }

    public function testAWaiverWorksWithoutADispatcher(): void
    {
        $store = new Store();
        $enrollments = new Enrollments($store->courseStore, $store->enrollmentStore, $store->clock);
        $chances = new SecondChances($store->retryStore, $store->courses, $enrollments, $store->availabilities, $store->clock);
        $course = $store->courses->create('Quiet');
        $module = $store->courses->addModule($course->id, 'M');
        $exam = $store->courses->addActivity($module->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 1);
        $enrollment = $enrollments->enroll($course->id, LearnerId::of('ada'));

        $enrollments->record($enrollment, $exam, Outcome::failed());
        $chances->waive($chances->request($enrollment, $exam->id, 'Ill')->id, 'teacher-1');

        self::assertTrue($store->enrollmentStore->progress($enrollment->id, $exam->id)?->waived);
        self::assertSame([], $store->events->events);
    }

    public function testTheReviewSkipsARequestWhoseRecordIsGoneAndKeepsTheOnesAfterIt(): void
    {
        $store = new Store();
        $course = $store->courses->create('Exam');
        $module = $store->courses->addModule($course->id, 'M');
        $exam = $store->courses->addActivity($module->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 1);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->enrollments->record($enrollment, $exam, Outcome::failed());
        $earlier = $store->clock->now()->modify('-1 hour');
        $store->retryStore->save(new RetryRequest(RetryRequestId::of('gone-enrollment'), EnrollmentId::of('gone'), $exam->id, '', $earlier));
        $store->retryStore->save(new RetryRequest(RetryRequestId::of('gone-activity'), $enrollment->id, ActivityId::of('gone'), '', $earlier));

        $kept = $store->secondChances->request($enrollment, $exam->id, 'Ill');

        self::assertSame([$kept->id->value], array_map(static fn (RetryReview $review): string => $review->request->id->value, $store->secondChances->review()));
    }

    public function testTheEntryPointsWorkWithoutADispatcher(): void
    {
        $store = new Store();
        $enrollments = new Enrollments($store->courseStore, $store->enrollmentStore, $store->clock);
        $chances = new SecondChances($store->retryStore, $store->courses, $enrollments, $store->availabilities, $store->clock);
        $course = $store->courses->create('Quiet');
        $module = $store->courses->addModule($course->id, 'M');
        $exam = $store->courses->addActivity($module->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 1);
        $enrollment = $enrollments->enroll($course->id, LearnerId::of('ada'));

        $enrollments->record($enrollment, $exam, Outcome::failed());
        $first = $chances->request($enrollment, $exam->id, 'Ill');
        $chances->grant($first->id, 'teacher-1');
        $enrollments->record($enrollment, $exam, Outcome::failed());
        $chances->refuse($chances->request($enrollment, $exam->id, 'Again')->id, 'teacher-1');
        $enrollments->record($enrollment, $exam, Outcome::passed());

        self::assertTrue($enrollments->get($enrollment->id)->isCompleted());
        self::assertSame([], $store->events->events);
    }

    public function testAnOutcomeOfAnotherMomentIsKeptAtThatMoment(): void
    {
        $store = new Store();
        $course = $store->courses->create('Past');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'page');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        $progress = $store->enrollments->record($enrollment, $activity, Outcome::completed(), new DateTimeImmutable('2026-01-02 03:04:05 UTC'));

        self::assertSame('2026-01-02T03:04:05+00:00', $progress->satisfiedAt?->format(\DATE_ATOM));
    }

    public function testTheTriesAddUpAndAGrantSatisfiesNothing(): void
    {
        $store = new Store();
        $course = $store->courses->create('Tries');
        $module = $store->courses->addModule($course->id, 'M');
        $exam = $store->courses->addActivity($module->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 2);
        $store->courses->addActivity($module->id, 'Other', 'page');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        $store->enrollments->record($enrollment, $exam, Outcome::failed());
        self::assertSame(AvailabilityStatus::Open, $store->availabilities->of($enrollment, $exam->id)->availability->status);
        $store->enrollments->record($enrollment, $exam, Outcome::failed());
        self::assertSame(AvailabilityStatus::RetryNeeded, $store->availabilities->of($enrollment, $exam->id)->availability->status);

        $store->enrollments->grantTry($enrollment, $exam);
        self::assertSame([], $store->events->of(ActivitySatisfied::class));
        self::assertFalse($store->enrollments->get($enrollment->id)->isCompleted());
    }

    public function testACourseIsCompletedOnlyWhenEveryRequiredActivityIsSatisfied(): void
    {
        $store = new Store();
        $course = $store->courses->create('Two');
        $module = $store->courses->addModule($course->id, 'M');
        $first = $store->courses->addActivity($module->id, 'First', 'page');
        $store->courses->addActivity($module->id, 'Second', 'page');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        $store->learning->complete($enrollment, $first->id);

        self::assertFalse($store->enrollments->get($enrollment->id)->isCompleted());
    }

    public function testAClosedActivityNamesItsStatusInWords(): void
    {
        $store = new Store();
        $course = $store->courses->create('Exam');
        $exam = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'Exam', 'page', [], CompletionRequirement::Passed, true, 1);
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->enrollments->record($enrollment, $exam, Outcome::failed());

        $this->expectException(ActivityUnavailable::class);
        $this->expectExceptionMessageIsOrContains('This activity is not open: it is retry needed.');

        $store->learning->launch($enrollment, $exam->id);
    }

    public function testTheLaterReleaseOfTheModuleAndTheActivityWins(): void
    {
        $store = new Store();
        $course = $store->courses->create('Later');
        $module = $store->courses->addModule($course->id, 'M', Release::daysAfterEnrollment(3));
        $soon = $store->courses->addActivity($module->id, 'Soon', 'page', [], CompletionRequirement::Completed, true, null, Release::daysAfterEnrollment(1));
        $late = $store->courses->addActivity($module->id, 'Late', 'page', [], CompletionRequirement::Completed, true, null, Release::daysAfterEnrollment(5));
        $open = $store->courses->addActivity($store->courses->addModule($course->id, 'Open')->id, 'Open', 'page');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));

        self::assertSame('2026-11-16T05:00:00+00:00', $store->availabilities->of($enrollment, $soon->id)->availability->opensAt?->format(\DATE_ATOM));
        self::assertSame('2026-11-18T05:00:00+00:00', $store->availabilities->of($enrollment, $late->id)->availability->opensAt?->format(\DATE_ATOM));
        self::assertNull($store->availabilities->of($enrollment, $open->id)->availability->opensAt);
    }

    public function testAnOutlineListsEachModuleItsOwnActivitiesWithoutGaps(): void
    {
        $store = new Store();
        $course = $store->courses->create('Outline');
        $first = $store->courses->addModule($course->id, 'First');
        $second = $store->courses->addModule($course->id, 'Second');
        $store->courses->addActivity($first->id, 'A', 'page');
        $b = $store->courses->addActivity($second->id, 'B', 'page');

        self::assertSame([$b->id->value], array_map(static fn (Activity $activity): string => $activity->id->value, $store->courses->outline($course->id)->modules[1]->activities));
        self::assertSame([0], array_keys($store->courses->outline($course->id)->modules[1]->activities));
    }

    public function testATypeOfANumericNameIsNamedAsText(): void
    {
        $numeric = new class implements ActivityType {
            #[Override]
            public function name(): string
            {
                return '42';
            }

            #[Override]
            public function settings(array $settings): array
            {
                return $settings;
            }

            #[Override]
            public function completesManually(): bool
            {
                return true;
            }

            #[Override]
            public function launch(Activity $activity, Enrollment $enrollment): Launch
            {
                return new PageActivity()->launch($activity, $enrollment);
            }
        };

        self::assertSame(['42'], new ActivityTypes([$numeric])->names());
    }

    /**
     * @return CourseRepository&object{calls: list<array{int, int}>}
     */
    private function recordingCourses(CourseRepository $inner): CourseRepository
    {
        return new class($inner) implements CourseRepository {
            /** @var list<array{int, int}> */
            public array $calls = [];

            public function __construct(
                private readonly CourseRepository $inner,
            ) {
            }

            #[Override]
            public function save(Course $course): void
            {
                $this->inner->save($course);
            }

            #[Override]
            public function saveModule(Module $module): void
            {
                $this->inner->saveModule($module);
            }

            #[Override]
            public function saveActivity(Activity $activity): void
            {
                $this->inner->saveActivity($activity);
            }

            #[Override]
            public function saveBlock(Block $block): void
            {
                $this->inner->saveBlock($block);
            }

            #[Override]
            public function blocks(CourseId $course): array
            {
                return $this->inner->blocks($course);
            }

            #[Override]
            public function find(CourseId $id): ?Course
            {
                return $this->inner->find($id);
            }

            #[Override]
            public function module(ModuleId $id): ?Module
            {
                return $this->inner->module($id);
            }

            #[Override]
            public function activity(ActivityId $id): ?Activity
            {
                return $this->inner->activity($id);
            }

            #[Override]
            public function modules(CourseId $course): array
            {
                return $this->inner->modules($course);
            }

            #[Override]
            public function activities(CourseId $course): array
            {
                return $this->inner->activities($course);
            }

            #[Override]
            public function all(int $limit = 50, int $offset = 0): array
            {
                $this->calls[] = [$limit, $offset];

                return [];
            }

            #[Override]
            public function remove(CourseId $id): void
            {
                $this->inner->remove($id);
            }
        };
    }
}
