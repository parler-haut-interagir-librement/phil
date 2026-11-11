<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application;

use DateTimeImmutable;
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Runs every check, one after the other, and times each on the application's clock. A check that throws counts as
 * down: its exception is logged, and the report names only its class, never its message, which may carry a host or a
 * credential. Nothing is kept from one round to the next.
 */
final readonly class HealthChecker
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        private iterable $checks,
        private ClockInterface $clock,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function report(): HealthReport
    {
        $checkedAt = $this->clock->now();
        $results = [];

        foreach ($this->checks as $check) {
            $results[] = $this->run($check);
        }

        return new HealthReport($results, $checkedAt);
    }

    private function run(HealthCheck $check): CheckResult
    {
        $start = $this->clock->now();

        try {
            $outcome = $check->check();
        } catch (Throwable $throwable) {
            $this->logger?->error('The health check {check} failed.', ['check' => $check->name(), 'exception' => $throwable]);
            $outcome = CheckOutcome::down(\sprintf('The check failed with %s.', substr((string) strrchr('\\'.$throwable::class, '\\'), 1)));
        }

        return new CheckResult($check->name(), $outcome, $this->elapsed($start));
    }

    /**
     * The milliseconds since `$start`, from the microseconds of both instants; 0 when the clock went back.
     */
    private function elapsed(DateTimeImmutable $start): int
    {
        return max(0, intdiv((int) $this->clock->now()->format('Uu') - (int) $start->format('Uu'), 1000));
    }
}
