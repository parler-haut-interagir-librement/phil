<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Model\LearningActivity;
use Phil\CertificatesBundle\Domain\Status\CertificateStatus;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\Period;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCertificateRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineCourseRepository;
use Phil\CertificatesBundle\Infrastructure\Doctrine\DoctrineLearningActivityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The instants of the register — awards, ends of validity, revocations, reminders, ends of courses, completed
 * activities — written by a process in one time zone and read by a process in another: the same instants, and the
 * queries that compare them find the same rows.
 */
abstract class StoredInstantsTestCase extends TestCase
{
    protected EntityManagerInterface $entityManager;

    private string $defaultZone = 'UTC';

    #[Override]
    protected function setUp(): void
    {
        $this->defaultZone = date_default_timezone_get();
        $this->entityManager = $this->entityManager();
    }

    #[Override]
    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultZone);
    }

    /**
     * @return iterable<string, array{non-empty-string, non-empty-string}>
     */
    public static function zones(): iterable
    {
        yield 'written in Toronto, read in UTC' => ['America/Toronto', 'UTC'];
        yield 'written in UTC, read in Toronto' => ['UTC', 'America/Toronto'];
        yield 'written in Tokyo, read in Toronto' => ['Asia/Tokyo', 'America/Toronto'];
    }

    /**
     * @param non-empty-string $writer
     * @param non-empty-string $reader
     */
    #[DataProvider('zones')]
    public function testTheInstantsReadBackAreTheOnesWrittenWhateverTheTimeZoneOfTheProcess(string $writer, string $reader): void
    {
        $courses = new DoctrineCourseRepository($this->entityManager);
        $certificates = new DoctrineCertificateRepository($this->entityManager);
        $activities = new DoctrineLearningActivityRepository($this->entityManager);

        date_default_timezone_set($writer);
        $now = new DateTimeImmutable('2026-07-01 12:00:00');
        $course = Course::define('botany', 'Botany', '', 'Pass.', validityMonths: 12, endsAt: $now->modify('+5 years'));
        $courses->save($course);

        $expired = $this->award($course, 'AAAAAAAAAAA1', 1, $now->modify('-1 year -1 minute'));
        $valid = $this->award($course, 'AAAAAAAAAAA2', 2, $now->modify('-1 year +1 minute'));
        $valid->remind($now->modify('-3 hours'));

        $revoked = $this->award($course, 'AAAAAAAAAAA3', 3, $now->modify('-1 day'));
        $revoked->revoke('Fraud.', $now->modify('-2 hours'));

        foreach ([$expired, $valid, $revoked] as $certificate) {
            $certificates->save($certificate);
        }

        $member = MemberId::fromString('member-1');
        $activities->save(LearningActivity::record($member, 'Workshop', Credits::fromString('2'), $now->modify('-30 minutes')));
        $this->entityManager->clear();

        date_default_timezone_set($reader);
        $readNow = new DateTimeImmutable('@'.$now->getTimestamp())->setTimezone(new DateTimeZone($reader));
        $readExpired = $certificates->findByCode('AAAAAAAAAAA1') ?? self::fail('Kept.');
        $readValid = $certificates->findByCode('AAAAAAAAAAA2') ?? self::fail('Kept.');
        $readRevoked = $certificates->findByCode('AAAAAAAAAAA3') ?? self::fail('Kept.');
        $at = $now->getTimestamp();

        self::assertSame(
            [$at - 60, $at + 60, $at - 10_800, $at - 7200, new DateTimeImmutable('2031-07-01 12:00:00', new DateTimeZone($writer))->getTimestamp()],
            [$readExpired->validUntil?->getTimestamp(), $readValid->validUntil?->getTimestamp(), $readValid->remindedAt?->getTimestamp(), $readRevoked->revokedAt?->getTimestamp(), $readValid->course->endsAt?->getTimestamp()],
        );
        self::assertSame($at - 86_400, $readRevoked->awardedAt->getTimestamp());

        // An end passed in the writer's clock is passed in the reader's, and one to come is still to come.
        self::assertSame([CertificateStatus::Expired, CertificateStatus::Valid, CertificateStatus::Revoked], [$readExpired->status($readNow), $readValid->status($readNow), $readRevoked->status($readNow)]);

        // The queries compare in UTC too.
        self::assertSame(['AAAAAAAAAAA2'], array_map(static fn (Certificate $certificate): string => $certificate->code->value, $certificates->expiring($readNow, $readNow->modify('+1 hour'), 10)));
        self::assertSame(['AAAAAAAAAAA1'], array_map(static fn (Certificate $certificate): string => $certificate->code->value, $certificates->expiring($readNow->modify('-1 hour'), $readNow, 10)));
        self::assertSame('2', $activities->total($member, new Period($readNow->modify('-1 hour'), $readNow))->formatted());
        self::assertSame('0', $activities->total($member, new Period($readNow->modify('-29 minutes'), $readNow))->formatted());
        self::assertSame(['Workshop'], array_map(static fn (LearningActivity $activity): string => $activity->title, $activities->of($member, new Period($readNow->modify('-31 minutes'), $readNow), 10)));
    }

    public function testTheTwoOneThirtiesOfTheAutumnChangeOfTimeStayAnHourApart(): void
    {
        $courses = new DoctrineCourseRepository($this->entityManager);
        $certificates = new DoctrineCertificateRepository($this->entityManager);

        date_default_timezone_set('America/Toronto');
        // 01:30 daylight time, then 01:30 standard time, on the night the clocks go back.
        $first = new DateTimeImmutable('2026-11-01 05:30:00 UTC')->setTimezone(new DateTimeZone('America/Toronto'));
        $second = new DateTimeImmutable('2026-11-01 06:30:00 UTC')->setTimezone(new DateTimeZone('America/Toronto'));
        self::assertSame($first->format('Y-m-d H:i'), $second->format('Y-m-d H:i'));

        $course = Course::define('botany', 'Botany', '', 'Pass.', endsAt: $second);
        $courses->save($course);
        $certificates->save($this->award($course, 'AAAAAAAAAAA1', 1, $first));
        $this->entityManager->clear();

        $read = $certificates->findByCode('AAAAAAAAAAA1') ?? self::fail('Kept.');
        self::assertSame([1_793_511_000, 1_793_514_600, 1_793_514_600], [$read->awardedAt->getTimestamp(), $read->validUntil?->getTimestamp(), $read->course->endsAt?->getTimestamp()]);
        // Between the two, the certificate is still valid.
        self::assertSame(CertificateStatus::Valid, $read->status(new DateTimeImmutable('@'.($first->getTimestamp() + 3540))));
        self::assertSame(CertificateStatus::Expired, $read->status($second));
    }

    /**
     * An entity manager over an empty schema of the register.
     */
    abstract protected function entityManager(): EntityManagerInterface;

    private function award(Course $course, string $code, int $index, DateTimeImmutable $awardedAt): Certificate
    {
        return Certificate::award('id-'.$code, VerificationCode::fromString($code), $course, 'Ada', $awardedAt, $index, 'a.b.c', true);
    }
}
