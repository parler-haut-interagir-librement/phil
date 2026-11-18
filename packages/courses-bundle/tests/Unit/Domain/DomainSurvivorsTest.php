<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Model\Activity;
use Phil\CoursesBundle\Domain\Model\Module;
use Phil\CoursesBundle\Domain\Model\Progress;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Release;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The defaults and the exact bounds of the models.
 */
#[CoversClass(Activity::class)]
#[CoversClass(Module::class)]
#[CoversClass(Progress::class)]
#[CoversClass(Outcome::class)]
final class DomainSurvivorsTest extends TestCase
{
    public function testAnActivityIsRequiredAndOpenByDefaultAndKeepsItsRelease(): void
    {
        $activity = $this->activity();
        $released = $this->activity(release: Release::daysAfterEnrollment(3));

        self::assertTrue($activity->required);
        self::assertNull($activity->release->days);
        self::assertSame(3, $released->release->days);
        self::assertSame(3, new Module(ModuleId::of('m'), CourseId::of('c'), 'M', 0, Release::daysAfterEnrollment(3))->release->days);
    }

    public function testTheTriesOfTheLimitAreAllowed(): void
    {
        self::assertSame(1, $this->activity(maxAttempts: 1)->maxAttempts);
        self::assertSame(Activity::MAX_ATTEMPTS, $this->activity(maxAttempts: Activity::MAX_ATTEMPTS)->maxAttempts);
    }

    public function testSettingsOfTheLimitAreCountedAsTheyAreKept(): void
    {
        $settings = ['f' => 1.0, 'path' => '/é'];
        $base = \strlen((string) json_encode($settings, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION));
        $settings['path'] = str_repeat('x', Activity::SETTINGS_LENGTH - $base).'/é';

        self::assertSame($settings, $this->activity($settings)->settings);

        $this->expectException(InvalidCourse::class);
        $settings['path'] .= 'x';
        $this->activity($settings);
    }

    public function testAProgressIsNotWaivedByDefault(): void
    {
        self::assertFalse(new Progress(EnrollmentId::of('e'), ActivityId::of('a'), new DateTimeImmutable('2026-11-13 UTC'))->waived);
    }

    public function testAFailureIsNotACompletion(): void
    {
        self::assertFalse(Outcome::failed()->completed);
    }

    /**
     * @param array<string, bool|float|int|string|null> $settings
     */
    private function activity(array $settings = [], ?int $maxAttempts = null, ?Release $release = null): Activity
    {
        return new Activity(ActivityId::of('a'), CourseId::of('c'), ModuleId::of('m'), 'A', 'page', 0, $settings, maxAttempts: $maxAttempts, release: $release);
    }
}
