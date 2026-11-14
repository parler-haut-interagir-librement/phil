<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\CertificatesBundle\Application\ExpiryReminders;
use Phil\CertificatesBundle\Domain\Event\CertificateExpiring;
use Phil\CertificatesBundle\Domain\Exception\InvalidValue;
use Phil\CertificatesBundle\Domain\Model\Certificate;
use Phil\CertificatesBundle\Domain\Model\Course;
use Phil\CertificatesBundle\Domain\ValueObject\VerificationCode;
use Phil\CertificatesBundle\Tests\Unit\Support\InMemoryCertificates;
use Phil\CertificatesBundle\Tests\Unit\Support\RecordedEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(ExpiryReminders::class)]
#[CoversClass(CertificateExpiring::class)]
final class ExpiryRemindersTest extends TestCase
{
    private InMemoryCertificates $certificates;

    private RecordedEvents $events;

    private MockClock $clock;

    private Course $course;

    #[Override]
    protected function setUp(): void
    {
        $this->certificates = new InMemoryCertificates();
        $this->events = new RecordedEvents();
        $this->clock = new MockClock(new DateTimeImmutable('2027-01-01 00:00:00 UTC'));
        $this->course = Course::define('first-aid', 'First aid', '', 'Pass.', validityMonths: 12);
    }

    public function testEachThresholdIsRemindedOnce(): void
    {
        // Ends on 2027-03-02 00:00 UTC.
        $certificate = $this->certificate('2026-03-02 00:00:00', 0);
        $reminders = $this->reminders();

        $this->clock->modify('2026-12-31 23:59:59 UTC');
        self::assertSame(0, $reminders->remind());

        $this->clock->modify('2027-01-01 00:00:00 UTC');
        self::assertSame(1, $reminders->remind());
        self::assertSame(0, $reminders->remind());
        self::assertEquals([new CertificateExpiring($certificate->id, $certificate->code->value, 'first-aid', 60)], $this->events->events);
        self::assertSame('2027-01-01', $certificate->remindedAt?->format('Y-m-d'));

        $this->clock->modify('2027-01-30 23:59:59 UTC');
        self::assertSame(0, $reminders->remind());
        $this->clock->modify('2027-01-31 00:00:00 UTC');
        self::assertSame(1, $reminders->remind());
        $this->clock->modify('2027-02-23 00:00:00 UTC');
        self::assertSame(1, $reminders->remind());
        self::assertSame(0, $reminders->remind());
        self::assertSame([60, 30, 7], array_map(static fn (object $event): int => $event instanceof CertificateExpiring ? $event->daysLeft : -1, $this->events->events));

        $this->clock->modify('2027-03-02 00:00:00 UTC');
        self::assertSame(0, $reminders->remind());
        self::assertSame(3, $this->certificates->saved);
    }

    public function testACertificateFoundLateIsRemindedOnceAtItsNearestThreshold(): void
    {
        $certificate = $this->certificate('2026-03-02 00:00:00', 0);
        $this->clock->modify('2027-02-25 12:00:00 UTC');

        self::assertSame(1, $this->reminders()->remind());
        self::assertSame(0, $this->reminders()->remind());
        self::assertEquals([new CertificateExpiring($certificate->id, $certificate->code->value, 'first-aid', 4)], $this->events->events);
    }

    public function testRevokedRenewedAndEndlessCertificatesAreNotReminded(): void
    {
        $revoked = $this->certificate('2026-02-01 00:00:00', 0);
        $revoked->revoke('Fraud.', new DateTimeImmutable('2026-06-01 UTC'));

        $renewed = $this->certificate('2026-02-01 00:00:00', 1);
        $this->certificates->save(Certificate::award('0190f1d2-3c4b-7a8e-9f00-1122334455ff', VerificationCode::generate(), $this->course, 'Ada', new DateTimeImmutable('2027-01-01 UTC'), 2, 'h.p.s', renews: $renewed));
        $this->certificates->save(Certificate::award('0190f1d2-3c4b-7a8e-9f00-1122334455fe', VerificationCode::generate(), Course::define('cpr', 'CPR', '', 'Pass.'), 'Ada', new DateTimeImmutable('2026-02-01 UTC'), 3, 'h.p.s'));

        self::assertSame(0, $this->reminders()->remind());
        self::assertSame([], $this->events->events);
    }

    public function testARunRemindsUpToItsLimitAndTheNextRunTheRest(): void
    {
        for ($index = 0; $index < ExpiryReminders::PAGE + 5; ++$index) {
            $this->certificate('2026-02-15 00:00:00', $index);
        }

        self::assertSame(ExpiryReminders::PAGE + 2, $this->reminders()->remind(ExpiryReminders::PAGE + 2));
        self::assertSame(3, $this->reminders()->remind());
        self::assertSame(0, $this->reminders()->remind());
        self::assertCount(ExpiryReminders::PAGE + 5, $this->events->events);
    }

    public function testTheThresholdsAreSortedAndTheirDuplicatesDropped(): void
    {
        $certificate = $this->certificate('2026-01-20 00:00:00', 0);
        $reminders = $this->reminders([7, 30, 30]);

        self::assertSame('2026-12-21', $reminders->reachedAt($certificate, $this->clock->now())?->format('Y-m-d'));
        $this->clock->modify('2027-01-13 00:00:00 UTC');
        self::assertSame('2027-01-13', $reminders->reachedAt($certificate, $this->clock->now())?->format('Y-m-d'));
        self::assertNull($reminders->reachedAt($certificate, new DateTimeImmutable('2026-12-20 23:59:59 UTC')));
        self::assertNull($reminders->reachedAt($certificate, new DateTimeImmutable('2027-01-20 00:00:00 UTC')));
    }

    public function testTheDefaultThresholdsAre60And30And7DaysBeforeTheEnd(): void
    {
        $reminders = new ExpiryReminders($this->certificates, $this->clock, $this->events);
        $now = $this->clock->now();

        // The days left before the end, and the days before now at which the nearest threshold was reached: none when no
        // threshold is.
        foreach ([[61, null], [60, 0], [31, -29], [30, 0], [8, -22], [7, 0], [1, -6]] as $index => [$left, $reached]) {
            $certificate = $this->certificateEndingIn($left, $index);

            self::assertSame(
                null === $reached ? null : $now->modify(\sprintf('%+d days', $reached))->format('Y-m-d H:i'),
                $reminders->reachedAt($certificate, $now)?->format('Y-m-d H:i'),
                \sprintf('A certificate with %d days left.', $left),
            );
        }
    }

    public function testARunRemindsAThousandCertificatesAtMostByDefault(): void
    {
        for ($index = 0; $index < 1002; ++$index) {
            $this->certificate('2026-02-15 00:00:00', $index);
        }

        self::assertSame(1000, $this->reminders()->remind());
        self::assertSame(2, $this->reminders()->remind());
    }

    public function testThePagesAreReadOnceEachAndOnlyAsFarAsTheLimit(): void
    {
        $this->certificates->readLimit = 10;

        for ($index = 0; $index < (2 * ExpiryReminders::PAGE) + 5; ++$index) {
            $this->certificate('2026-02-15 00:00:00', $index);
        }

        self::assertSame((2 * ExpiryReminders::PAGE) + 5, $this->reminders()->remind());
        self::assertSame(3, $this->certificates->reads);
    }

    public function testAShortPageEndsTheRun(): void
    {
        $this->certificates->readLimit = 10;
        $this->certificate('2026-02-15 00:00:00', 0);

        self::assertSame(1, $this->reminders()->remind());
        self::assertSame(1, $this->certificates->reads);
    }

    public function testARunThatReachedItsLimitReadsNoMorePage(): void
    {
        $this->certificates->readLimit = 10;

        for ($index = 0; $index < ExpiryReminders::PAGE + 5; ++$index) {
            $this->certificate('2026-02-15 00:00:00', $index);
        }

        self::assertSame(ExpiryReminders::PAGE, $this->reminders()->remind(ExpiryReminders::PAGE));
        self::assertSame(1, $this->certificates->reads);
    }

    /**
     * @return iterable<string, array{list<int>, string}>
     */
    public static function invalidThresholds(): iterable
    {
        yield 'none' => [[], 'The reminders have one threshold at least.'];
        yield 'at the end' => [[30, 0], 'A reminder comes from 1 to 3650 days before the end; 0 is not.'];
        yield 'too early' => [[3651], 'A reminder comes from 1 to 3650 days before the end; 3651 is not.'];
    }

    /**
     * @param list<int> $thresholds
     */
    #[DataProvider('invalidThresholds')]
    public function testThresholdsOutOfRangeAreRefused(array $thresholds, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->reminders($thresholds);
    }

    public function testTheExtremeThresholdsAreAccepted(): void
    {
        $certificate = $this->certificate('2026-01-01 12:00:00', 0);

        self::assertSame(1, $this->reminders([1, 3650])->remind());
        self::assertSame(0, $this->events->events[0] instanceof CertificateExpiring ? $this->events->events[0]->daysLeft : -1);
        self::assertSame('2027-01-01', $certificate->remindedAt?->format('Y-m-d'));
    }

    private function certificateEndingIn(int $days, int $index): Certificate
    {
        $end = $this->clock->now()->modify(\sprintf('+%d days', $days));

        return $this->certificate($end->modify('-12 months')->format('Y-m-d H:i:s'), $index);
    }

    private function certificate(string $awardedAt, int $index): Certificate
    {
        $certificate = Certificate::award(\sprintf('0190f1d2-3c4b-7a8e-9f00-%012d', $index), VerificationCode::generate(), $this->course, 'Ada', new DateTimeImmutable($awardedAt.' UTC'), $index, 'h.p.s');
        $this->certificates->save($certificate);
        $this->certificates->saved = 0;

        return $certificate;
    }

    /**
     * @param list<int> $thresholds
     */
    private function reminders(array $thresholds = [60, 30, 7]): ExpiryReminders
    {
        return new ExpiryReminders($this->certificates, $this->clock, $this->events, $thresholds);
    }
}
