<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CertificatesBundle\Application\Courses;
use Phil\CertificatesBundle\Application\Revocation;
use Phil\CertificatesBundle\Domain\Event\CertificateRevoked;
use Phil\CertificatesBundle\Domain\Exception\CertificateRefused;
use Phil\CertificatesBundle\Domain\ValueObject\PassCriteria;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCourses;
use Phil\CertificatesBundle\Tests\Unit\Support\Kit;
use Phil\CertificatesBundle\Tests\Unit\Support\RecordedEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Courses::class)]
#[CoversClass(Revocation::class)]
final class CoursesAndRevocationTest extends TestCase
{
    public function testACourseIsDefinedOnceRevisedRetiredAndReopened(): void
    {
        $repository = new InMemoryCourses();
        $courses = new Courses($repository);

        $course = $courses->define('first-aid', 'First aid', 'The gestures.', 'Score 70 %.', new PassCriteria(70), 12, 'https://example.com/a.png');
        self::assertSame($course, $repository->findBySlug('first-aid'));
        self::assertSame(12, $course->validityMonths);
        self::assertSame('https://example.com/a.png', $course->image);

        self::assertSame($course, $courses->revise('first-aid', 'First aid 2', 'New.', 'Score 80 %.', new PassCriteria(80), 6, 'https://example.com/b.png'));
        self::assertSame(['First aid 2', 'New.', 'Score 80 %.', 80, 6, 'https://example.com/b.png'], [$course->name, $course->description, $course->narrative, $course->criteria()->minimumScore, $course->validityMonths, $course->image]);
        self::assertTrue($courses->retire('first-aid')->retired);
        self::assertFalse($courses->reopen('first-aid')->retired);
        self::assertSame(4, $repository->saved);
        self::assertSame($course, $courses->get('first-aid'));

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('A course "first-aid" already exists.');

        $courses->define('first-aid', 'Again', '', 'Pass.');
    }

    public function testAnUnknownCourseIsRefused(): void
    {
        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('There is no course "cpr".');

        new Courses(new InMemoryCourses())->retire('cpr');
    }

    public function testARevocationWithoutADispatcherStillRevokes(): void
    {
        $certificates = new InMemoryCertificates();
        $certificates->save(Kit::awarded());

        $certificate = new Revocation($certificates, new MockClock(new DateTimeImmutable('2026-12-01 12:00 UTC')))->revoke('7KQ2-M9XA-04TZ', 'Fraud.');

        self::assertSame('Fraud.', $certificate->revocationReason);
    }

    public function testACertificateIsRevokedByItsCodeAsPrinted(): void
    {
        $certificates = new InMemoryCertificates();
        $certificates->save(Kit::awarded());

        $events = new RecordedEvents();
        $revocation = new Revocation($certificates, new MockClock(new DateTimeImmutable('2026-12-01 12:00 UTC')), $events);

        $certificate = $revocation->revoke('7kq2-m9xa-04tz', 'Fraud.');
        self::assertEquals([new CertificateRevoked('0190f1d2-3c4b-7a8e-9f00-112233445566', '7KQ2M9XA04TZ', 'first-aid')], $events->events);

        self::assertSame('2026-12-01 12:00', $certificate->revokedAt?->format('Y-m-d H:i'));
        self::assertSame('Fraud.', $certificate->revocationReason);
        self::assertSame(2, $certificates->saved);

        $this->expectException(CertificateRefused::class);
        $this->expectExceptionMessageIsOrContains('There is no certificate 0000-0000-0000.');

        $revocation->revoke('0000-0000-0000', 'Fraud.');
    }
}
