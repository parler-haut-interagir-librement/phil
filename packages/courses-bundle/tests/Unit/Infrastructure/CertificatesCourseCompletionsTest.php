<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Infrastructure;

use Phil\CertificatesBundle\Domain\Contract\CourseCompletions;
use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Infrastructure\Certificates\CertificatesCourseCompletions;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The port of phil/certificates-bundle answered from the enrollments.
 */
#[CoversClass(CertificatesCourseCompletions::class)]
final class CertificatesCourseCompletionsTest extends TestCase
{
    public function testTheCompletionIsTheEnrollmentThatCompletedTheCourse(): void
    {
        $store = new Store();
        $course = $store->courses->create('Knots');
        $activity = $store->courses->addActivity($store->courses->addModule($course->id, 'Basics')->id, 'Reef knot', 'page');
        $adapter = new CertificatesCourseCompletions(new Completions($store->enrollmentStore));

        self::assertInstanceOf(CourseCompletions::class, $adapter);
        self::assertNull($adapter->completion($course->id->value, 'ada'));

        $enrollment = $store->enrollments->enroll($course->id, LearnerId::of('ada'));
        self::assertNull($adapter->completion($course->id->value, 'ada'));

        $store->learning->complete($enrollment, $activity->id);
        self::assertSame($enrollment->id->value, $adapter->completion($course->id->value, 'ada'));
        self::assertNull($adapter->completion($course->id->value, 'grace'));
    }

    public function testAnIdentifierNoBundleCouldHoldNamesNoCompletion(): void
    {
        $adapter = new CertificatesCourseCompletions(new Completions(new Store()->enrollmentStore));

        self::assertNull($adapter->completion('', 'ada'));
        self::assertNull($adapter->completion('course', ''));
        self::assertNull($adapter->completion(str_repeat('c', 1000), 'ada'));
    }
}
