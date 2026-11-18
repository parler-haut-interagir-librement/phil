<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Application;

use Phil\CoursesBundle\Application\ActivityTypes;
use Phil\CoursesBundle\Application\CourseOutline;
use Phil\CoursesBundle\Application\Courses;
use Phil\CoursesBundle\Application\ModuleOutline;
use Phil\CoursesBundle\Domain\Course\CourseOrigin;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Exception\UnknownRecord;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Course;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Progress\CompletionRequirement;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Release;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Courses::class)]
#[CoversClass(CourseOutline::class)]
#[CoversClass(ModuleOutline::class)]
#[CoversClass(ActivityTypes::class)]
#[CoversClass(UnknownRecord::class)]
final class CoursesTest extends TestCase
{
    public function testACourseIsWrittenModuleByModuleAndReadAsAnOutline(): void
    {
        $store = new Store();
        $course = $store->courses->create('Navigation', 'Charts and tides', true, CourseOrigin::Cmi5, 'https://example.org/c');
        $first = $store->courses->addModule($course->id, 'Charts');
        $second = $store->courses->addModule($course->id, 'Tides', Release::daysAfterEnrollment(2));
        $tides = $store->courses->addActivity($second->id, 'Tide tables', 'page', ['body' => 'High and low.'], CompletionRequirement::Completed, false, 2, Release::daysAfterEnrollment(3), 'tides');
        $symbols = $store->courses->addActivity($first->id, 'Symbols', 'page');
        $legend = $store->courses->addActivity($first->id, 'Legend', 'link', ['url' => 'HTTPS://Example.org/legend']);

        self::assertSame([0, 1], [$first->position, $second->position]);
        self::assertSame([0, 1, 0], [$symbols->position, $legend->position, $tides->position]);
        self::assertSame('https://example.org/legend', $legend->setting('url'));
        self::assertSame(['body' => ''], $symbols->settings);

        $outline = $store->courses->outline($course->id);
        self::assertSame('Navigation', $outline->course->title);
        self::assertSame(['Charts', 'Tides'], array_map(static fn (ModuleOutline $module): string => $module->module->title, $outline->modules));
        self::assertSame(['Symbols', 'Legend'], array_map(static fn (Activity $activity): string => $activity->title, $outline->modules[0]->activities));
        self::assertSame(['Symbols', 'Legend', 'Tide tables'], array_map(static fn (Activity $activity): string => $activity->title, $outline->activities()));
        self::assertSame(2, $outline->modules[1]->module->release->days);

        $read = $store->courses->activity($tides->id);
        self::assertSame('High and low.', $read->setting('body'));
        self::assertFalse($read->required);
        self::assertSame(2, $read->maxAttempts);
        self::assertSame(3, $read->release->days);
        self::assertSame('tides', $read->source);
        self::assertTrue($store->courses->get($course->id)->ordered);
        self::assertSame(CourseOrigin::Cmi5, $store->courses->get($course->id)->origin);
        self::assertSame([], CourseOutline::of($course, [], [])->activities());
    }

    public function testCoursesArePagedTheLatestFirst(): void
    {
        $store = new Store();
        $first = $store->courses->create('First');
        $store->clock->modify('+1 minute');
        $second = $store->courses->create('Second');

        self::assertSame([$second->id->value, $first->id->value], array_map(static fn (Course $course): string => $course->id->value, $store->courses->all()));
        self::assertSame([$first->id->value], array_map(static fn (Course $course): string => $course->id->value, $store->courses->all(1, 1)));
        self::assertCount(1, $store->courses->all(0));
        self::assertCount(2, $store->courses->all(500, -3));
    }

    public function testAnUnknownCourseModuleOrActivityIsReported(): void
    {
        $store = new Store();

        foreach ([
            'No course "c" is known.' => static fn (): Course => $store->courses->get(CourseId::of('c')),
            'No course "c2" is known.' => static fn (): Module => $store->courses->addModule(CourseId::of('c2'), 'M'),
            'No module "m" is known.' => static fn (): Activity => $store->courses->addActivity(ModuleId::of('m'), 'A', 'page'),
            'No activity "a" is known.' => static fn (): Activity => $store->courses->activity(ActivityId::of('a')),
            'No course "c3" is known.' => static fn (): CourseOutline => $store->courses->outline(CourseId::of('c3')),
        ] as $message => $call) {
            try {
                $call();
                self::fail('Nothing is reported.');
            } catch (UnknownRecord $unknownRecord) {
                self::assertSame($message, $unknownRecord->getMessage());
            }
        }
    }

    public function testAnActivityOfAnUnknownTypeOrWrongSettingsIsRefused(): void
    {
        $store = new Store();
        $module = $store->courses->addModule($store->courses->create('C')->id, 'M');

        self::assertSame(['page', 'link', 'scorm', 'cmi5'], $store->types->names());
        self::assertTrue($store->types->has('page'));
        self::assertFalse($store->types->has('quiz'));

        try {
            $store->courses->addActivity($module->id, 'A', 'quiz');
            self::fail('The type is accepted.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame('No activity type is named "quiz"; the types are: page, link, scorm, cmi5.', $invalidCourse->getMessage());
        }

        $this->expectException(InvalidCourse::class);
        $store->courses->addActivity($module->id, 'A', 'link', ['url' => 'not a url']);
    }

    public function testACourseHoldsBoundedModulesAndActivities(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');

        for ($index = 0; $index < Courses::MAX_MODULES; ++$index) {
            $store->courseStore->saveModule(new Module(ModuleId::of('m'.$index), $course->id, 'M', $index));
        }

        try {
            $store->courses->addModule($course->id, 'One too many');
            self::fail('The module is accepted.');
        } catch (InvalidCourse $invalidCourse) {
            self::assertSame('A course holds 500 modules at most.', $invalidCourse->getMessage());
        }

        for ($index = 0; $index < Courses::MAX_ACTIVITIES; ++$index) {
            $store->courseStore->saveActivity(new Activity(ActivityId::of('a'.$index), $course->id, ModuleId::of('m0'), 'A', 'page', $index));
        }

        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains('A module holds 500 activities at most.');

        $store->courses->addActivity(ModuleId::of('m0'), 'One too many', 'page');
    }

    public function testACourseIsRemovedWithEverythingOfIt(): void
    {
        $store = new Store();
        $course = $store->courses->create('C');
        $kept = $store->courses->create('Kept');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'M')->id, 'A', 'page');
        $store->courses->addActivity($store->courses->addModule($kept->id, 'M')->id, 'A', 'page');
        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        $store->learning->complete($enrollment, $activity->id);

        $store->courses->remove($course->id);

        self::assertNull($store->courseStore->find($course->id));
        self::assertSame(0, Store::count($store->connection, 'SELECT COUNT(*) FROM phil_course_progress'));
        self::assertSame(1, Store::count($store->connection, 'SELECT COUNT(*) FROM phil_course_activity'));
        self::assertSame(1, Store::count($store->connection, 'SELECT COUNT(*) FROM phil_course_module'));
        self::assertSame(0, Store::count($store->connection, 'SELECT COUNT(*) FROM phil_course_enrollment'));
    }
}
