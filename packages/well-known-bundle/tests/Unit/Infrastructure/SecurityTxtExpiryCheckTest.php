<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Infrastructure\Monitoring\SecurityTxtExpiryCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SecurityTxtExpiryCheck::class)]
final class SecurityTxtExpiryCheckTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, HealthStatus, string}>
     */
    public static function moments(): iterable
    {
        yield 'far' => ['2027-01-01T00:00:00Z', 30, HealthStatus::Up, 'Expires on 2027-07-01T00:00:00+00:00.'];
        yield 'a second before the warning' => ['2027-05-31T23:59:59Z', 30, HealthStatus::Up, 'Expires on 2027-07-01T00:00:00+00:00.'];
        yield 'on the warning' => ['2027-06-01T00:00:00Z', 30, HealthStatus::Degraded, 'The security.txt expires on 2027-07-01T00:00:00+00:00, in 30 days.'];
        yield 'two days and a second left' => ['2027-06-28T23:59:59Z', 30, HealthStatus::Degraded, 'The security.txt expires on 2027-07-01T00:00:00+00:00, in 3 days.'];
        yield 'two days left' => ['2027-06-29T00:00:00Z', 30, HealthStatus::Degraded, 'The security.txt expires on 2027-07-01T00:00:00+00:00, in 2 days.'];
        yield 'an hour left' => ['2027-06-30T23:00:00Z', 30, HealthStatus::Degraded, 'The security.txt expires on 2027-07-01T00:00:00+00:00, in 1 day.'];
        yield 'a second left' => ['2027-06-30T23:59:59Z', 30, HealthStatus::Degraded, 'The security.txt expires on 2027-07-01T00:00:00+00:00, in 1 day.'];
        yield 'expiring' => ['2027-07-01T00:00:00Z', 30, HealthStatus::Down, 'The security.txt expired on 2027-07-01T00:00:00+00:00.'];
        yield 'expired' => ['2028-01-01T00:00:00Z', 30, HealthStatus::Down, 'The security.txt expired on 2027-07-01T00:00:00+00:00.'];
        yield 'a warning of one day, two days left' => ['2027-06-29T00:00:00Z', 1, HealthStatus::Up, 'Expires on 2027-07-01T00:00:00+00:00.'];
        yield 'a warning of one day, one day left' => ['2027-06-30T00:00:00Z', 1, HealthStatus::Degraded, 'The security.txt expires on 2027-07-01T00:00:00+00:00, in 1 day.'];
    }

    #[DataProvider('moments')]
    public function testTheStatusFollowsTheTimeLeft(string $now, int $warningDays, HealthStatus $status, string $summary): void
    {
        $check = new SecurityTxtExpiryCheck(new DateTimeImmutable('2027-07-01T00:00:00Z'), new MockClock($now), $warningDays);

        $outcome = $check->check();

        self::assertSame([$status, $summary], [$outcome->status, $outcome->summary]);
    }

    public function testTheDateIsShownInUtcAndTheWarningIsThirtyDaysByDefault(): void
    {
        $check = new SecurityTxtExpiryCheck(new DateTimeImmutable('2027-07-01T02:00:00+02:00'), new MockClock('2027-06-01T00:00:00Z'));

        self::assertSame('security_txt', $check->name());
        self::assertSame('The security.txt expires on 2027-07-01T00:00:00+00:00, in 30 days.', $check->check()->summary);
        self::assertSame(HealthStatus::Up, new SecurityTxtExpiryCheck(new DateTimeImmutable('2027-07-01T00:00:00Z'), new MockClock('2027-05-31T23:59:59Z'))->check()->status);
    }

    public function testAWarningOfLessThanADayIsRefused(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('The warning before the security.txt expires is a number of days, 1 or more; 0 is not.');

        new SecurityTxtExpiryCheck(new DateTimeImmutable('2027-07-01T00:00:00Z'), new MockClock(), 0);
    }
}
