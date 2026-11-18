<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;
use Phil\CoursesBundle\Domain\Identifiers;
use Phil\CoursesBundle\Domain\Progress\AvailabilityStatus;
use Phil\CoursesBundle\Domain\ValueObject\ActivityId;
use Phil\CoursesBundle\Domain\ValueObject\Availability;
use Phil\CoursesBundle\Domain\ValueObject\BlockId;
use Phil\CoursesBundle\Domain\ValueObject\CourseId;
use Phil\CoursesBundle\Domain\ValueObject\EnrollmentId;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;
use Phil\CoursesBundle\Domain\ValueObject\ModuleId;
use Phil\CoursesBundle\Domain\ValueObject\Outcome;
use Phil\CoursesBundle\Domain\ValueObject\Release;
use Phil\CoursesBundle\Domain\ValueObject\RetryRequestId;
use Phil\CoursesBundle\Domain\ValueObject\Score;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Identifiers::class)]
#[CoversClass(CourseId::class)]
#[CoversClass(ModuleId::class)]
#[CoversClass(BlockId::class)]
#[CoversClass(ActivityId::class)]
#[CoversClass(EnrollmentId::class)]
#[CoversClass(RetryRequestId::class)]
#[CoversClass(LaunchSessionId::class)]
#[CoversClass(LearnerId::class)]
#[CoversClass(Release::class)]
#[CoversClass(Score::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(Availability::class)]
final class ValuesTest extends TestCase
{
    public function testIdentifiersAreDrawnReadBackAndCompared(): void
    {
        foreach ([CourseId::class, ModuleId::class, BlockId::class, ActivityId::class, EnrollmentId::class, RetryRequestId::class, LaunchSessionId::class] as $class) {
            $id = $class::generate();
            self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id->value);
            self::assertTrue($class::of($id->value)->equals($id));
            self::assertFalse($class::generate()->equals($id));
            self::assertSame(str_repeat('a', 36), $class::of(str_repeat('a', 36))->value);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function wrongIdentifiers(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 37)];
        yield 'leading dot' => ['.abc'];
        yield 'slash' => ['a/b'];
        yield 'space' => ['a b'];
        yield 'new line at the end' => ["abc\n"];
    }

    #[DataProvider('wrongIdentifiers')]
    public function testAWrongIdentifierIsRefused(string $value): void
    {
        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains('A course identifier is 1 to 36 letters');

        CourseId::of($value);
    }

    public function testALearnerIsAStableIdentifierNeverAnEmail(): void
    {
        self::assertSame('ada.lovelace_1:x-2', LearnerId::of('ada.lovelace_1:x-2')->value);
        self::assertSame(str_repeat('b', 190), LearnerId::of(str_repeat('b', 190))->value);
        self::assertTrue(LearnerId::of('ada')->equals(LearnerId::of('ada')));
        self::assertFalse(LearnerId::of('ada')->equals(LearnerId::of('bob')));

        $this->expectException(InvalidCourse::class);
        $this->expectExceptionMessageIsOrContains('A learner identifier is 1 to 190');

        LearnerId::of('ada@example.org');
    }

    public function testALearnerIdentifierIsBounded(): void
    {
        $this->expectException(InvalidCourse::class);

        LearnerId::of(str_repeat('b', 191));
    }

    public function testATitleIsTrimmedAndBounded(): void
    {
        self::assertSame('Navigation', Identifiers::title('  Navigation '));
        self::assertSame(str_repeat('é', 255), Identifiers::title(str_repeat('é', 255)));

        foreach (['   ', str_repeat('é', 256)] as $title) {
            try {
                Identifiers::title($title);
                self::fail('The title is accepted.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('A title is 1 to 255 characters.', $invalidCourse->getMessage());
            }
        }
    }

    public function testAReleaseOpensAtOnceAtAMomentOrDaysAfterTheEnrollmentAtMidnightOfTheSite(): void
    {
        $toronto = new DateTimeZone('America/Toronto');
        $enrolled = new DateTimeImmutable('2026-11-13 03:30:00 UTC');

        self::assertTrue(Release::immediately()->isImmediate());
        self::assertNull(Release::immediately()->opensAt($enrolled, $toronto));
        self::assertTrue(Release::immediately()->isOpen($enrolled, $enrolled, $toronto));

        $on = Release::on(new DateTimeImmutable('2026-12-01 09:00', $toronto));
        self::assertFalse($on->isImmediate());
        self::assertSame('2026-12-01T14:00:00+00:00', $on->opensAt($enrolled, $toronto)?->format(\DATE_ATOM));
        self::assertSame('UTC', $on->on?->getTimezone()->getName());
        self::assertFalse($on->isOpen($enrolled, new DateTimeImmutable('2026-12-01 13:59:59 UTC'), $toronto));
        self::assertTrue($on->isOpen($enrolled, new DateTimeImmutable('2026-12-01 14:00:00 UTC'), $toronto));

        // Enrolled at 22:30 on the 12th in Toronto: day 3 opens at midnight of the 15th there.
        $days = Release::daysAfterEnrollment(3);
        self::assertFalse($days->isImmediate());
        self::assertSame(3, $days->days);
        self::assertSame('2026-11-15T05:00:00+00:00', $days->opensAt($enrolled, $toronto)?->format(\DATE_ATOM));
        self::assertSame('2026-11-12T05:00:00+00:00', Release::daysAfterEnrollment(0)->opensAt($enrolled, $toronto)?->format(\DATE_ATOM));
        self::assertSame(3650, Release::daysAfterEnrollment(3650)->days);
    }

    public function testAReleaseIsZeroToTenYearsOfDays(): void
    {
        foreach ([-1, 3651] as $days) {
            try {
                Release::daysAfterEnrollment($days);
                self::fail('The days are accepted.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('A release is 0 to 3650 days after the enrollment.', $invalidCourse->getMessage());
            }
        }
    }

    public function testAScoreIsScaledOrOutOfAHundred(): void
    {
        self::assertSame(-1.0, Score::scaled(-1.0)->scaled);
        self::assertSame(1.0, Score::scaled(1.0)->scaled);
        self::assertSame(0.8, Score::percent(80.0)->scaled);
        self::assertSame(0.0, Score::percent(0.0)->scaled);
        self::assertSame(1.0, Score::percent(100.0)->scaled);
        self::assertTrue(Score::scaled(0.8)->reaches(0.8));
        self::assertFalse(Score::scaled(0.79)->reaches(0.8));

        foreach ([-1.01, 1.01, \NAN] as $wrong) {
            try {
                Score::scaled($wrong);
                self::fail('The score is accepted.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('A scaled score is between -1 and 1.', $invalidCourse->getMessage());
            }
        }

        foreach ([-0.1, 100.1, \NAN] as $wrong) {
            try {
                Score::percent($wrong);
                self::fail('The score is accepted.');
            } catch (InvalidCourse $invalidCourse) {
                self::assertSame('A score out of a hundred is between 0 and 100.', $invalidCourse->getMessage());
            }
        }
    }

    public function testAnOutcomeSaysWhatTheLearnerDid(): void
    {
        $score = Score::scaled(0.5);

        self::assertTrue(new Outcome()->isEmpty());
        self::assertFalse(Outcome::completed()->isEmpty());
        self::assertTrue(Outcome::completed()->completed);
        self::assertNull(Outcome::completed()->success);
        self::assertTrue(Outcome::passed($score)->success);
        self::assertFalse(Outcome::passed()->completed);
        self::assertSame($score, Outcome::passed($score)->score);
        self::assertFalse(Outcome::failed($score)->success);
        self::assertFalse(Outcome::failed()->isEmpty());
        self::assertSame($score, Outcome::failed($score)->score);
    }

    /**
     * @return iterable<string, array{Availability, AvailabilityStatus, bool}>
     */
    public static function availabilities(): iterable
    {
        yield 'open' => [Availability::open(), AvailabilityStatus::Open, true];
        yield 'satisfied' => [Availability::satisfied(), AvailabilityStatus::Satisfied, true];
        yield 'scheduled' => [Availability::scheduled(new DateTimeImmutable('2026-12-01 UTC')), AvailabilityStatus::Scheduled, false];
        yield 'locked' => [Availability::waitingFor(ActivityId::of('a1')), AvailabilityStatus::Locked, false];
        yield 'out of tries' => [Availability::outOfTries(false), AvailabilityStatus::RetryNeeded, false];
        yield 'asked for' => [Availability::outOfTries(true), AvailabilityStatus::RetryRequested, false];
    }

    #[DataProvider('availabilities')]
    public function testAnAvailabilitySaysWhetherTheLearnerCanStart(Availability $availability, AvailabilityStatus $status, bool $canStart): void
    {
        self::assertSame($status, $availability->status);
        self::assertSame($canStart, $availability->canStart());
    }

    public function testAnAvailabilitySaysWhatIsLeft(): void
    {
        self::assertSame('2026-12-01', Availability::scheduled(new DateTimeImmutable('2026-12-01 UTC'))->opensAt?->format('Y-m-d'));
        self::assertSame('a1', Availability::waitingFor(ActivityId::of('a1'))->waitingFor?->value);
        self::assertNull(Availability::open()->opensAt);
        self::assertNull(Availability::open()->waitingFor);
    }
}
