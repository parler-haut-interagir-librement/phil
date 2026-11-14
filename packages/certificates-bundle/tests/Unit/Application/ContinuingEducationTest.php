<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\ContinuingEducation;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Domain\Event\LearningActivityRecorded;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\Period;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryActivities;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use Phil\CertificatesBundle\Tests\Unit\Support\RecordedEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ContinuingEducation::class)]
#[CoversClass(LearningActivityRecorded::class)]
final class ContinuingEducationTest extends TestCase
{
    private InMemoryActivities $activities;

    private InMemoryCourses $courses;

    private InMemoryCertificates $certificates;

    private RecordedEvents $events;

    private MockClock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->activities = new InMemoryActivities();
        $this->courses = new InMemoryCourses();
        $this->courses->save(Course::define('first-aid', 'First aid', '', 'Pass.', validityMonths: 24, recertificationCredits: Credits::fromString('12')));
        $this->courses->save(Course::define('cpr', 'CPR', '', 'Pass.'));

        $this->certificates = new InMemoryCertificates();
        $this->events = new RecordedEvents();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
    }

    public function testAnActivityIsRecordedNowOrOnTheDayGivenAndDispatched(): void
    {
        $now = $this->education()->record('member-1', 'Triage', Credits::fromString('1.5'));
        $then = $this->education()->record('member-1', 'CPR refresher', Credits::fromString('2'), new DateTimeImmutable('2026-10-01 UTC'), 'cpr');

        self::assertSame($now, $this->activities->find($now->id));
        self::assertSame('2026-11-08 10:00', $now->completedAt->format('Y-m-d H:i'));
        self::assertNull($now->course);
        self::assertSame($this->courses->courses['cpr'], $then->course);
        self::assertEquals([new LearningActivityRecorded($now->id, 'member-1'), new LearningActivityRecorded($then->id, 'member-1')], $this->events->events);
    }

    public function testAnActivityIsRecordedWithoutADispatcher(): void
    {
        $education = new ContinuingEducation($this->activities, new Courses($this->courses), $this->certificates, $this->clock);

        $activity = $education->record('member-1', 'Triage', Credits::fromString('1.5'));

        self::assertSame($activity, $this->activities->find($activity->id));
        self::assertSame([], $this->events->events);
    }

    public function testTheSameSourceIsRecordedOnceForAMember(): void
    {
        $first = $this->education()->record('member-1', 'Workshop', Credits::fromString('3'), source: 'workshop-17');
        $again = $this->education()->record('member-1', 'Workshop, again', Credits::fromString('30'), source: 'workshop-17');
        $other = $this->education()->record('member-2', 'Workshop', Credits::fromString('3'), source: 'workshop-17');

        self::assertSame($first, $again);
        self::assertNotSame($first, $other);
        self::assertCount(2, $this->activities->activities);
        self::assertCount(2, $this->events->events);
    }

    public function testAMemberIsAStableIdentifierAndACourseMustExist(): void
    {
        try {
            $this->education()->record('ada@example.org', 'Workshop', Credits::fromString('3'));
            self::fail('Refused.');
        } catch (InvalidValue) {
        }

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('There is no course "alchemy".');

        $this->education()->record('member-1', 'Workshop', Credits::fromString('3'), course: 'alchemy');
    }

    public function testActivitiesAreRemoved(): void
    {
        $activity = $this->education()->record('member-1', 'Workshop', Credits::fromString('3'));

        self::assertSame($activity, $this->education()->remove($activity->id));
        self::assertSame([], $this->activities->activities);

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('There is no activity %s.', $activity->id));

        $this->education()->remove($activity->id);
    }

    public function testCreditsAreListedAndSummedOverAPeriodAndTowardACourse(): void
    {
        $education = $this->education();
        $education->record('member-1', 'General', Credits::fromString('1'), new DateTimeImmutable('2026-02-01 UTC'));
        $education->record('member-1', 'First aid', Credits::fromString('2.5'), new DateTimeImmutable('2026-03-01 UTC'), 'first-aid');
        $education->record('member-1', 'CPR', Credits::fromString('4'), new DateTimeImmutable('2026-04-01 UTC'), 'cpr');
        $education->record('member-1', 'Last year', Credits::fromString('8'), new DateTimeImmutable('2025-12-31 23:59:59 UTC'));
        $education->record('member-2', 'Another member', Credits::fromString('16'), new DateTimeImmutable('2026-04-01 UTC'));

        $year = new Period(new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC'));

        self::assertSame('7.5', $education->total('member-1', $year)->formatted());
        self::assertSame('3.5', $education->total('member-1', $year, 'first-aid')->formatted());
        self::assertSame('5', $education->total('member-1', $year, 'cpr')->formatted());
        self::assertSame(['CPR', 'First aid', 'General'], array_map(static fn (LearningActivity $activity): string => $activity->title, $education->activities('member-1', $year)));
        self::assertSame([ContinuingEducation::MAX_LISTED], $this->activities->limits);
    }

    public function testTheProgressOfAHolderSinceTheCertificateWasAwarded(): void
    {
        $certificate = $this->certificate('member-1');
        $education = $this->education();
        $education->record('member-1', 'Before', Credits::fromString('50'), new DateTimeImmutable('2026-11-08 09:59:59 UTC'));
        $education->record('member-1', 'Since', Credits::fromString('4.5'), new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $education->record('member-1', 'CPR', Credits::fromString('50'), new DateTimeImmutable('2026-12-01 UTC'), 'cpr');

        $this->clock->modify('2027-01-01 UTC');
        $education->record('member-1', 'Today', Credits::fromString('1'));

        $progress = $education->progress($certificate->code->formatted());

        self::assertSame(['5.5', '12', '6.5'], [$progress['gathered']->formatted(), $progress['required']?->formatted(), $progress['missing']?->formatted()]);
    }

    public function testTheProgressOfACourseWithoutCreditsMissesNothing(): void
    {
        $certificate = $this->certificate('member-1', 'cpr');

        $progress = $this->education()->progress($certificate->code->value);

        self::assertSame('0', $progress['gathered']->formatted());
        self::assertNull($progress['required']);
        self::assertNull($progress['missing']);
    }

    public function testTheProgressAsksForACertificateWithAHolder(): void
    {
        $certificate = $this->certificate(null);

        try {
            $this->education()->progress($certificate->code->value);
            self::fail('Refused.');
        } catch (CertificateRefused $certificateRefused) {
            self::assertSame(\sprintf('The certificate %s names no holder whose credits are counted.', $certificate->code->formatted()), $certificateRefused->getMessage());
        }

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('There is no certificate 0000-0000-0000.');

        $this->education()->progress('0000-0000-0000');
    }

    private function certificate(?string $holder, string $course = 'first-aid'): Certificate
    {
        $certificate = Certificate::award('0190f1d2-3c4b-7a8e-9f00-112233445566', VerificationCode::generate(), $this->courses->courses[$course], 'Ada', new DateTimeImmutable('2026-11-08 10:00:00 UTC'), 0, 'h.p.s', holder: null === $holder ? null : MemberId::fromString($holder));
        $this->certificates->save($certificate);

        return $certificate;
    }

    private function education(): ContinuingEducation
    {
        return new ContinuingEducation($this->activities, new Courses($this->courses), $this->certificates, $this->clock, $this->events);
    }
}
