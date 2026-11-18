<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Enrollment;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\Model\RetryRequest;
use Phil\CoursesBundle\Domain\Progress\RetryDecision;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Phil\CoursesBundle\Infrastructure\Doctrine\CourseTables;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalCourseRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalEnrollmentRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalLaunchSessionRepository;
use Phil\CoursesBundle\Infrastructure\Doctrine\DbalRetryRequestRepository;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * What the stores write and read on their own: the types of the columns, the parameters of each query, the order and
 * bounds of the lists, and a course removed with every row of it.
 */
#[CoversClass(DbalCourseRepository::class)]
#[CoversClass(DbalEnrollmentRepository::class)]
#[CoversClass(DbalRetryRequestRepository::class)]
#[CoversClass(DbalLaunchSessionRepository::class)]
final class StoreSurvivorsTest extends TestCase
{
    private const string MOMENT = '2026-11-13 10:00:00 UTC';

    public function testTheBooleansAreWrittenAsBooleans(): void
    {
        $store = new Store();
        $course = new Course(CourseId::of('c'), 'C', new DateTimeImmutable(self::MOMENT));
        $store->courseStore->save($course);
        $store->courseStore->saveModule(new Module(ModuleId::of('m'), $course->id, 'M', 0));
        $store->courseStore->saveActivity(new Activity(ActivityId::of('a'), $course->id, ModuleId::of('m'), 'A', 'page', 0, [], required: false));
        $store->enrollmentStore->saveProgress(new Progress(EnrollmentId::of('e'), ActivityId::of('a'), new DateTimeImmutable(self::MOMENT)));
        $store->sessionStore->save(new LaunchSession(LaunchSessionId::of('s'), EnrollmentId::of('e'), ActivityId::of('a'), new DateTimeImmutable(self::MOMENT), new DateTimeImmutable(self::MOMENT)));

        foreach ([CourseTables::COURSE => 'ordered', CourseTables::ACTIVITY => 'required', CourseTables::PROGRESS => 'completed', CourseTables::SESSION => 'fetched'] as $table => $column) {
            self::assertSame('integer', $store->connection->fetchOne('SELECT typeof('.$column.') FROM '.$table), $table.'.'.$column);
        }
    }

    public function testTheSettingsAreWrittenAsTheyAreAndNestedOnesAreNotRead(): void
    {
        $store = new Store();
        $activity = new Activity(ActivityId::of('a'), CourseId::of('c'), ModuleId::of('m'), 'A', 'page', 0, ['path' => 'a/é.html', 'weight' => 1.0]);
        $store->courseStore->saveActivity($activity);

        self::assertSame('{"path":"a/é.html","weight":1.0}', $store->connection->fetchOne('SELECT settings FROM '.CourseTables::ACTIVITY));

        $store->connection->update(CourseTables::ACTIVITY, ['settings' => '{"url":["https://example.org/"]}'], ['id' => $activity->id->value]);
        self::assertSame([], $store->courseStore->activity($activity->id)?->settings);
    }

    public function testTheModulesAndActivitiesOfACourseAreReadInOrder(): void
    {
        $store = new Store();
        $course = $store->courses->create('C', '', false, CourseOrigin::Scorm12, 'pkg');
        $first = $store->courses->addModule($course->id, 'First');
        $second = $store->courses->addModule($course->id, 'Second');
        $a = $store->courses->addActivity($second->id, 'A', 'page');
        $b = $store->courses->addActivity($first->id, 'B', 'page');
        $other = $store->courses->create('Other');
        $store->courses->addActivity($store->courses->addModule($other->id, 'O')->id, 'O', 'page');

        $modules = $store->courseStore->modules($course->id);
        self::assertContainsOnlyInstancesOf(Module::class, $modules);
        self::assertSame([$first->id->value, $second->id->value], array_map(static fn (Module $module): string => $module->id->value, $modules));
        $activities = $store->courseStore->activities($course->id);
        self::assertContainsOnlyInstancesOf(Activity::class, $activities);
        self::assertSame([$b->id->value, $a->id->value], array_map(static fn (Activity $activity): string => $activity->id->value, $activities));
        self::assertSame(0, $activities[0]->position);
        self::assertSame(CourseOrigin::Scorm12, $store->courseStore->find($course->id)?->origin);
    }

    public function testANegativePositionIsReadAsTheFirst(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $module = $store->courses->addModule($course->id, 'M');
        $activity = $store->courses->addActivity($module->id, 'A', 'page');
        $store->connection->update(CourseTables::MODULE, ['position' => -3], ['id' => $module->id->value]);
        $store->connection->update(CourseTables::ACTIVITY, ['position' => -3], ['id' => $activity->id->value]);

        self::assertSame(0, $store->courseStore->module($module->id)?->position);
        self::assertSame(0, $store->courseStore->activity($activity->id)?->position);
    }

    public function testACourseIsRemovedWithEveryRowOfIt(): void
    {
        $store = new Store();
        $kept = $this->course($store, 'kept');
        $gone = $this->course($store, 'gone');

        $store->courseStore->remove(CourseId::of('gone'));

        self::assertNull($store->courseStore->find(CourseId::of('gone')));
        self::assertNotNull($store->courseStore->find(CourseId::of('kept')));

        foreach ([
            CourseTables::MODULE => 'course_id', CourseTables::ACTIVITY => 'course_id', CourseTables::ENROLLMENT => 'course_id',
            CourseTables::PROGRESS => 'enrollment_id', CourseTables::RETRY => 'enrollment_id', CourseTables::SESSION => 'enrollment_id',
        ] as $table => $column) {
            $value = 'course_id' === $column ? ['gone', 'kept'] : [$gone, $kept];
            self::assertSame(0, Store::count($store->connection, 'SELECT COUNT(*) FROM '.$table.' WHERE '.$column.' = ?', [$value[0]]), $table);
            self::assertSame(1, Store::count($store->connection, 'SELECT COUNT(*) FROM '.$table.' WHERE '.$column.' = ?', [$value[1]]), $table);
        }
    }

    public function testAnEnrollmentIsFoundForItsCourseAndLearnerWithEachProgress(): void
    {
        $store = new Store();
        $enrollment = new Enrollment(EnrollmentId::of('e'), CourseId::of('c'), LearnerId::of('ada'), new DateTimeImmutable(self::MOMENT));
        $store->enrollmentStore->save($enrollment);
        $store->enrollmentStore->save(new Enrollment(EnrollmentId::of('e2'), CourseId::of('c2'), LearnerId::of('ada'), new DateTimeImmutable(self::MOMENT)));

        self::assertSame('e', $store->enrollmentStore->findFor(CourseId::of('c'), LearnerId::of('ada'))?->id->value);
        self::assertNull($store->enrollmentStore->findFor(CourseId::of('c'), LearnerId::of('bob')));

        foreach (['a1', 'a2'] as $activity) {
            $store->enrollmentStore->saveProgress(new Progress($enrollment->id, ActivityId::of($activity), new DateTimeImmutable(self::MOMENT)));
        }

        self::assertSame(['a1', 'a2'], array_keys($store->enrollmentStore->progresses($enrollment->id)));
        self::assertNull($store->enrollmentStore->progress($enrollment->id, ActivityId::of('a1'))?->score);
    }

    public function testTheWaitingRequestsArePendingOldestFirstAndBounded(): void
    {
        $store = new Store();

        for ($index = 0; $index < 52; ++$index) {
            $store->retryStore->save(new RetryRequest(RetryRequestId::of(\sprintf('r%02d', $index)), EnrollmentId::of('e'.$index), ActivityId::of('a'), 'Reason', new DateTimeImmutable(self::MOMENT)->modify('+'.$index.' minutes')));
        }

        $decided = new RetryRequest(RetryRequestId::of('decided'), EnrollmentId::of('e'), ActivityId::of('a'), 'Reason', new DateTimeImmutable('2020-01-01 UTC'));
        $decided->decide(RetryDecision::Granted, 'teacher-1', new DateTimeImmutable(self::MOMENT));

        $store->retryStore->save($decided);

        $waiting = $store->retryStore->waiting();
        self::assertCount(50, $waiting);
        self::assertContainsOnlyInstancesOf(RetryRequest::class, $waiting);
        self::assertSame('r00', $waiting[0]->id->value);
        self::assertCount(1, $store->retryStore->waiting(0));
        self::assertCount(1, $store->retryStore->waiting(1));
    }

    public function testASessionJustLaunchedIsOpen(): void
    {
        $store = new Store();
        $store->sessionStore->save(new LaunchSession(LaunchSessionId::of('s'), EnrollmentId::of('e'), ActivityId::of('a'), new DateTimeImmutable(self::MOMENT), new DateTimeImmutable(self::MOMENT)));

        self::assertSame(['s'], array_map(static fn (LaunchSession $session): string => $session->id->value, $store->sessionStore->open(EnrollmentId::of('e'), ActivityId::of('a'))));
    }

    /**
     * A course with a module, an activity and an enrollment with its progress, a request and a session; returns the
     * enrollment's identifier.
     */
    private function course(Store $store, string $id): string
    {
        $moment = new DateTimeImmutable(self::MOMENT);
        $store->courseStore->save(new Course(CourseId::of($id), $id, $moment));
        $store->courseStore->saveModule(new Module(ModuleId::of($id.'-m'), CourseId::of($id), 'M', 0));
        $store->courseStore->saveActivity(new Activity(ActivityId::of($id.'-a'), CourseId::of($id), ModuleId::of($id.'-m'), 'A', 'page', 0));

        $enrollment = new Enrollment(EnrollmentId::of($id.'-e'), CourseId::of($id), LearnerId::of('ada'), $moment);
        $store->enrollmentStore->save($enrollment);
        $store->enrollmentStore->saveProgress(new Progress($enrollment->id, ActivityId::of($id.'-a'), $moment));
        $store->retryStore->save(new RetryRequest(RetryRequestId::of($id.'-r'), $enrollment->id, ActivityId::of($id.'-a'), 'Reason', $moment));
        $store->sessionStore->save(new LaunchSession(LaunchSessionId::of($id.'-s'), $enrollment->id, ActivityId::of($id.'-a'), $moment, $moment));

        return $enrollment->id->value;
    }
}
