<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Path;

use Phil\CoursesBundle\Application\Completions;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Infrastructure\Certificates\CertificatesCourseCompletions;
use Phil\CoursesBundle\Infrastructure\Privacy\PathsPrivacy;
use Phil\CoursesBundle\Tests\Unit\Fixture\Store;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The learning paths in the neighbouring bundles: their completions answered to phil/certificates-bundle, and their
 * enrollments in the access and erasure requests of phil/privacy-bundle.
 */
#[CoversClass(CertificatesCourseCompletions::class)]
#[CoversClass(PathsPrivacy::class)]
final class PathsInfrastructureTest extends TestCase
{
    public function testTheCompletionOfAPathIsItsEnrollment(): void
    {
        $store = new Store();
        $course = $store->courses->create('Knots');
        $page = $store->courses->addActivity($store->courses->addModule($course->id, 'Basics')->id, 'Reef knot', 'page');
        $path = $store->paths->create('Sailing');
        $store->paths->addStep($path->id, $course->id);
        $adapter = new CertificatesCourseCompletions($store->courseCompletions, $store->paths);
        $key = 'path:'.$path->id->value;

        self::assertNull($adapter->completion($key, 'ada'));
        $enrollment = $store->paths->enroll($path->id, LearnerId::of('ada'));
        self::assertNull($adapter->completion($key, 'ada'));
        $store->enrollments->record($store->enrollments->of($course->id, LearnerId::of('ada')) ?? self::fail('Not enrolled.'), $page, Outcome::completed());
        $store->paths->advance($enrollment);

        self::assertSame($enrollment->id->value, $adapter->completion($key, 'ada'));
        self::assertNull($adapter->completion($key, 'grace'));
        self::assertNull($adapter->completion('path:', 'ada'));
        self::assertNull($adapter->completion('path:'.str_repeat('p', 40), 'ada'));
        self::assertNull($adapter->completion($key, ''));
        self::assertNull(new CertificatesCourseCompletions(new Completions($store->enrollmentStore))->completion($key, 'ada'));
        // A course is still answered by its identifier.
        self::assertNotNull($adapter->completion($course->id->value, 'ada'));
    }

    public function testTheEnrollmentsInPathsAreExportedAndErased(): void
    {
        $store = new Store();
        $path = $store->paths->create('Sailing');
        $enrollment = $store->paths->enroll($path->id, LearnerId::of('ada'));
        $store->paths->enroll($path->id, LearnerId::of('grace'));
        $privacy = new PathsPrivacy($store->pathStore);

        self::assertSame(PathsPrivacy::MODULE, $privacy->module()->value);
        self::assertSame(['path_enrollments' => [[
            'enrollment' => $enrollment->id->value,
            'path' => $path->id->value,
            'enrolled_at' => '2026-11-13T10:00:00+00:00',
            'completed_at' => null,
        ]]], $privacy->export(DataSubject::fromString('ada')));
        self::assertSame([], $privacy->export(DataSubject::fromString('bob')));
        self::assertSame([], $privacy->export(DataSubject::fromString(str_repeat('x', 200))));

        self::assertSame(['phil_course_path_enrollment' => ErasureEffect::Deleted], $privacy->erase(DataSubject::fromString('ada'))->effects);
        self::assertSame([], $privacy->export(DataSubject::fromString('ada')));
        self::assertCount(1, $store->paths->ofLearner(LearnerId::of('grace')));
        self::assertSame(['phil_course_path_enrollment' => ErasureEffect::Deleted], $privacy->erase(DataSubject::fromString(str_repeat('x', 200)))->effects);
        self::assertCount(1, $store->paths->ofLearner(LearnerId::of('grace')));
    }
}
