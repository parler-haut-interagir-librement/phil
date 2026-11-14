<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\Status\CertificateStatus;
use Phil\CertificatesBundle\Domain\ValueObject\Credits;
use Phil\CertificatesBundle\Domain\ValueObject\MemberId;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The end of courses and certificates, their renewal and the reminders of their end.
 */
#[CoversClass(Course::class)]
#[CoversClass(Certificate::class)]
final class LifecycleTest extends TestCase
{
    public function testACourseKeepsTheCreditsOfItsRecertificationAndItsEnd(): void
    {
        $ends = new DateTimeImmutable('2030-01-01 UTC');
        $course = Course::define('first-aid', 'First aid', '', 'Pass.', new PassCriteria(), 24, null, Credits::fromString('12.5'), $ends);

        self::assertSame(1250, $course->recertificationCredits?->hundredths);
        self::assertSame($ends, $course->endsAt);
        self::assertFalse($course->hasEnded(new DateTimeImmutable('2029-12-31 23:59:59 UTC')));
        self::assertTrue($course->hasEnded($ends));
        self::assertTrue($course->hasEnded(new DateTimeImmutable('2030-01-02 UTC')));

        $course->revise('First aid', '', 'Pass.');
        self::assertNull($course->recertificationCredits);
        self::assertNull($course->endsAt);
        self::assertFalse($course->hasEnded(new DateTimeImmutable('2100-01-01 UTC')));
    }

    public function testARecertificationAsksForCreditsOrNone(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A recertification asks for more than 0 credits, or for none at all.');

        Course::define('first-aid', 'First aid', '', 'Pass.', recertificationCredits: Credits::zero());
    }

    public function testACertificateIsNeverValidPastTheEndOfItsCourse(): void
    {
        $awardedAt = new DateTimeImmutable('2026-11-08 10:00 UTC');
        $ends = new DateTimeImmutable('2027-06-01 UTC');

        self::assertSame('2027-06-01', Certificate::validUntil(Course::define('a', 'A', '', 'P.', validityMonths: 12, endsAt: $ends), $awardedAt)?->format('Y-m-d'));
        self::assertSame('2027-05-08', Certificate::validUntil(Course::define('a', 'A', '', 'P.', validityMonths: 6, endsAt: $ends), $awardedAt)?->format('Y-m-d'));
        self::assertSame('2027-06-01', Certificate::validUntil(Course::define('a', 'A', '', 'P.', endsAt: $ends), $awardedAt)?->format('Y-m-d'));
        self::assertSame('2027-06-08', Certificate::validUntil(Course::define('a', 'A', '', 'P.', validityMonths: 7, endsAt: new DateTimeImmutable('2027-06-08 10:00 UTC')), $awardedAt)?->format('Y-m-d'));
        self::assertNull(Certificate::validUntil(Course::define('a', 'A', '', 'P.'), $awardedAt));
    }

    public function testARenewalStartsFromTheEndOfTheCertificateItRenewsWhenThatEndIsToCome(): void
    {
        $course = Kit::firstAid(12);
        $first = Kit::awarded($course);

        self::assertSame('2027-11-08 10:00', $first->validUntil?->format('Y-m-d H:i'));
        self::assertSame('2028-11-08 10:00', Certificate::validUntil($course, new DateTimeImmutable('2027-10-01 UTC'), $first)?->format('Y-m-d H:i'));
        self::assertSame('2029-01-01 00:00', Certificate::validUntil($course, new DateTimeImmutable('2028-01-01 UTC'), $first)?->format('Y-m-d H:i'));
        self::assertSame('2028-11-08 10:00', Certificate::validUntil($course, new DateTimeImmutable('2027-11-08 10:00 UTC'), $first)?->format('Y-m-d H:i'));

        $forever = Kit::awarded(Kit::firstAid(null), code: 'AAAABBBBCCCC', index: 4);
        self::assertSame('2027-10-01', Certificate::validUntil($course, new DateTimeImmutable('2026-10-01 UTC'), $forever)?->format('Y-m-d'));
    }

    public function testARenewalNamesItsHolderAndTheCertificateItRenews(): void
    {
        $course = Kit::firstAid(12);
        $first = Kit::awarded($course);
        $renewal = Certificate::award('0190f1d2-3c4b-7a8e-9f00-112233445577', VerificationCode::fromString('AAAABBBBCCCC'), $course, 'Ada Lovelace', new DateTimeImmutable('2027-10-01 UTC'), 4, 'h.p.s', false, MemberId::fromString('member-1'), $first);

        self::assertSame($first, $renewal->renews);
        self::assertSame('member-1', $renewal->holder);
        self::assertSame('2028-11-08 10:00', $renewal->validUntil?->format('Y-m-d H:i'));
        self::assertNull($first->renews);
        self::assertNull($first->holder);
    }

    public function testACertificateRenewsOneOfItsOwnCourse(): void
    {
        $first = Kit::awarded(Kit::firstAid(12));

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A certificate renews a certificate of its own course.');

        Certificate::award('0190f1d2-3c4b-7a8e-9f00-112233445577', VerificationCode::fromString('AAAABBBBCCCC'), Course::define('cpr', 'CPR', '', 'Pass.'), 'Ada', new DateTimeImmutable('2027-10-01 UTC'), 4, 'h.p.s', renews: $first);
    }

    public function testTheDaysLeftAreWholeDaysUntilTheEnd(): void
    {
        $certificate = Kit::awarded(Kit::firstAid(12));

        self::assertSame(365, $certificate->daysLeft(new DateTimeImmutable('2026-11-08 10:00 UTC')));
        self::assertSame(30, $certificate->daysLeft(new DateTimeImmutable('2027-10-09 10:00 UTC')));
        self::assertSame(29, $certificate->daysLeft(new DateTimeImmutable('2027-10-09 10:00:01 UTC')));
        self::assertSame(0, $certificate->daysLeft(new DateTimeImmutable('2027-11-08 09:59:59 UTC')));
        self::assertNull($certificate->daysLeft(new DateTimeImmutable('2027-11-08 10:00 UTC')));
        self::assertNull(Kit::awarded(Kit::firstAid(null))->daysLeft(new DateTimeImmutable('2026-11-08 UTC')));
    }

    public function testAnExpiredCertificateIsNotRevokedAndARevokedOneStaysRevoked(): void
    {
        $certificate = Kit::awarded(Kit::firstAid(12));
        $after = new DateTimeImmutable('2028-01-01 UTC');

        self::assertSame(CertificateStatus::Expired, $certificate->status($after));
        self::assertNull($certificate->revokedAt);

        $certificate->revoke('Fraud.', $after);
        self::assertSame(CertificateStatus::Revoked, $certificate->status($after));
    }

    public function testTheReminderIsNoted(): void
    {
        $certificate = Kit::awarded();
        $at = new DateTimeImmutable('2027-09-09 UTC');

        self::assertNull($certificate->remindedAt);
        $certificate->remind($at);
        self::assertSame($at, $certificate->remindedAt);
    }
}
