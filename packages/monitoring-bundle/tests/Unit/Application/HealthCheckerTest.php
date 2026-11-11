<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Application;

use DateTimeImmutable;
use LogicException;
use Phil\MonitoringBundle\Application\HealthChecker;
use Phil\MonitoringBundle\Domain\Exception\InvalidValue;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\MonitoringBundle\Tests\Unit\Support\StubCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\Clock\MockClock;

#[CoversClass(HealthChecker::class)]
final class HealthCheckerTest extends TestCase
{
    public function testEveryCheckRunsInOrderAndIsTimed(): void
    {
        $clock = new MockClock('2026-11-08 10:00:00 UTC');
        $checker = new HealthChecker([
            new StubCheck('slow', static function () use ($clock): CheckOutcome {
                $clock->sleep(0.012_999);

                return CheckOutcome::degraded('slow');
            }),
            new StubCheck('exact', static function () use ($clock): CheckOutcome {
                $clock->sleep(0.013);

                return CheckOutcome::up();
            }),
            new StubCheck('back', static function () use ($clock): CheckOutcome {
                $clock->modify('-1 second');

                return CheckOutcome::up();
            }),
        ], $clock);

        $report = $checker->report();

        self::assertEquals(new DateTimeImmutable('2026-11-08 10:00:00 UTC'), $report->checkedAt);
        self::assertSame(['slow', 'exact', 'back'], array_map(static fn (CheckResult $result): string => $result->name, $report->results));
        self::assertSame([12, 13, 0], array_map(static fn (CheckResult $result): int => $result->durationMilliseconds, $report->results));
        self::assertSame(HealthStatus::Degraded, $report->status());
    }

    public function testACheckThatThrowsIsDownAndLoggedWithoutItsMessage(): void
    {
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{mixed, string, array<array-key, mixed>}>
             */
            public array $logs = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, (string) $message, $context];
            }
        };
        $exception = new RuntimeException('password=secret');
        $checker = new HealthChecker([new StubCheck('database', static fn (): CheckOutcome => throw $exception)], new MockClock(), $logger);

        $result = $checker->report()->results[0];

        self::assertSame(HealthStatus::Down, $result->status());
        self::assertSame('The check failed with RuntimeException.', $result->outcome->summary);
        self::assertSame([['error', 'The health check {check} failed.', ['check' => 'database', 'exception' => $exception]]], $logger->logs);
    }

    public function testAClassWithoutNamespaceIsNamedWhole(): void
    {
        $checker = new HealthChecker([new StubCheck('a', static fn (): CheckOutcome => throw new LogicException('x'))], new MockClock());

        self::assertSame('The check failed with LogicException.', $checker->report()->results[0]->outcome->summary);
    }

    public function testAnExceptionOfANamespaceIsNamedByItsShortName(): void
    {
        $checker = new HealthChecker([new StubCheck('a', static fn (): CheckOutcome => throw new InvalidValue('x'))], new MockClock());

        self::assertSame('The check failed with InvalidValue.', $checker->report()->results[0]->outcome->summary);
    }
}
