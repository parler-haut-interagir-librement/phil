<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\MonitoringBundle\Domain\Exception\InvalidValue;
use Phil\MonitoringBundle\Domain\Exception\MonitoringException;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Domain\Status\HeartbeatState;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\CheckResult;
use Phil\MonitoringBundle\Domain\ValueObject\ExpectedTask;
use Phil\MonitoringBundle\Domain\ValueObject\HealthReport;
use Phil\MonitoringBundle\Domain\ValueObject\HeartbeatStatus;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Phil\MonitoringBundle\Domain\ValueObject\Name;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(HealthStatus::class)]
#[CoversClass(CheckOutcome::class)]
#[CoversClass(CheckResult::class)]
#[CoversClass(HealthReport::class)]
#[CoversClass(Name::class)]
#[CoversClass(ExpectedTask::class)]
#[CoversClass(HeartbeatStatus::class)]
#[CoversClass(MaintenanceState::class)]
#[CoversClass(InvalidValue::class)]
final class ValueObjectsTest extends TestCase
{
    /**
     * @return iterable<string, array{HealthStatus, HealthStatus, HealthStatus}>
     */
    public static function pairs(): iterable
    {
        yield 'up, up' => [HealthStatus::Up, HealthStatus::Up, HealthStatus::Up];
        yield 'up, degraded' => [HealthStatus::Up, HealthStatus::Degraded, HealthStatus::Degraded];
        yield 'degraded, up' => [HealthStatus::Degraded, HealthStatus::Up, HealthStatus::Degraded];
        yield 'degraded, degraded' => [HealthStatus::Degraded, HealthStatus::Degraded, HealthStatus::Degraded];
        yield 'up, down' => [HealthStatus::Up, HealthStatus::Down, HealthStatus::Down];
        yield 'down, up' => [HealthStatus::Down, HealthStatus::Up, HealthStatus::Down];
        yield 'degraded, down' => [HealthStatus::Degraded, HealthStatus::Down, HealthStatus::Down];
        yield 'down, degraded' => [HealthStatus::Down, HealthStatus::Degraded, HealthStatus::Down];
    }

    #[DataProvider('pairs')]
    public function testTheWorstOfTwoStatuses(HealthStatus $one, HealthStatus $other, HealthStatus $worst): void
    {
        self::assertSame($worst, $one->worst($other));
    }

    public function testOnlyDownIsUnavailable(): void
    {
        self::assertTrue(HealthStatus::Up->isAvailable());
        self::assertTrue(HealthStatus::Degraded->isAvailable());
        self::assertFalse(HealthStatus::Down->isAvailable());
    }

    public function testAnOutcomeKeepsItsSummaryOnOneShortLine(): void
    {
        self::assertSame(HealthStatus::Up, CheckOutcome::up()->status);
        self::assertSame('', CheckOutcome::up()->summary);
        self::assertSame(HealthStatus::Degraded, CheckOutcome::degraded('slow')->status);
        self::assertSame(HealthStatus::Down, CheckOutcome::down('gone')->status);
        self::assertSame('3 messages waiting', CheckOutcome::up("  3\n messages\t\twaiting ")->summary);
        self::assertSame(str_repeat('é', 200), CheckOutcome::up(str_repeat('é', 201))->summary);
        self::assertSame(str_repeat('a', 200), CheckOutcome::up(str_repeat('a', 200))->summary);
    }

    public function testAResultNamesItsCheckAndKeepsItsDuration(): void
    {
        $result = new CheckResult('cache.app', CheckOutcome::degraded('slow'), 12);

        self::assertSame('cache.app', $result->name);
        self::assertSame(HealthStatus::Degraded, $result->status());
        self::assertSame('slow', $result->outcome->summary);
        self::assertSame(12, $result->durationMilliseconds);
        self::assertSame(0, new CheckResult('a', CheckOutcome::up(), 0)->durationMilliseconds);
    }

    public function testAResultTakesNoNegativeTime(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs('A check takes no less than 0 milliseconds.');

        new CheckResult('database', CheckOutcome::up(), -1);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'empty' => [''];
        yield 'leading space' => [' database'];
        yield 'trailing space' => ['database '];
        yield 'trailing newline' => ["database\n"];
        yield 'leading dot' => ['.database'];
        yield 'slash' => ['cache/app'];
        yield '101 characters' => [str_repeat('a', 101)];
    }

    #[DataProvider('badNames')]
    public function testANameIsAnIdentifier(string $name): void
    {
        $this->expectException(MonitoringException::class);
        $this->expectExceptionMessageIs(\sprintf('The check name "%s" is not an identifier of up to 100 letters, digits, dots, dashes, underscores and colons.', mb_substr($name, 0, 120)));

        Name::check($name, 'check');
    }

    public function testTheNameInTheMessageIsCut(): void
    {
        $this->expectExceptionMessageIsOrContains('The task name "'.str_repeat('é', 120).'" is not');

        Name::check(str_repeat('é', 130), 'task');
    }

    public function testTheNamesThatAreIdentifiers(): void
    {
        foreach (['a', '0', 'messenger.async', 'database.default', 'scheduler:main', 'my_task-2', str_repeat('a', 100)] as $name) {
            self::assertSame($name, Name::check($name, 'check'));
        }
    }

    public function testAReportIsAsHealthyAsItsWorstCheck(): void
    {
        $at = new DateTimeImmutable('2026-11-08 10:00:00 UTC');
        $up = new CheckResult('a', CheckOutcome::up(), 1);
        $degraded = new CheckResult('b', CheckOutcome::degraded('slow'), 1);
        $down = new CheckResult('c', CheckOutcome::down('gone'), 1);

        self::assertSame(HealthStatus::Up, new HealthReport([], $at)->status());
        self::assertSame(HealthStatus::Up, new HealthReport([$up], $at)->status());
        self::assertSame(HealthStatus::Degraded, new HealthReport([$up, $degraded, $up], $at)->status());
        self::assertSame(HealthStatus::Down, new HealthReport([$down, $degraded], $at)->status());
        self::assertSame($at, new HealthReport([$up], $at)->checkedAt);
        self::assertSame([$up, $down], new HealthReport([$up, $down], $at)->results);
    }

    public function testATaskIsLateOncePastItsPeriodAndGrace(): void
    {
        $task = new ExpectedTask('backup', 3600, 300);
        $beat = new DateTimeImmutable('2026-11-08 10:00:00 UTC');

        self::assertSame('backup', $task->name);
        self::assertSame(3600, $task->periodSeconds);
        self::assertSame(300, $task->graceSeconds);
        self::assertEquals(new DateTimeImmutable('2026-11-08 11:05:00 UTC'), $task->dueBy($beat));
        self::assertSame(HeartbeatState::Never, $task->stateAt(null, $beat));
        self::assertSame(HeartbeatState::OnTime, $task->stateAt($beat, $beat));
        self::assertSame(HeartbeatState::OnTime, $task->stateAt($beat, new DateTimeImmutable('2026-11-08 11:05:00 UTC')));
        self::assertSame(HeartbeatState::Late, $task->stateAt($beat, new DateTimeImmutable('2026-11-08 11:05:01 UTC')));
        self::assertSame(0, new ExpectedTask('t', 1)->graceSeconds);
        self::assertSame(1, new ExpectedTask('t', 1, 0)->periodSeconds);
    }

    public function testATaskRunsAtLeastOnceASecond(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs('The task "backup" runs at least once a second, not every 0 seconds.');

        new ExpectedTask('backup', 0);
    }

    public function testATaskHasNoNegativeGrace(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs('The grace of the task "backup" is 0 seconds or more, not -1.');

        new ExpectedTask('backup', 60, -1);
    }

    public function testATaskNameIsAnIdentifier(): void
    {
        $this->expectExceptionMessageIsOrContains('The task name "a b" is not');

        new ExpectedTask('a b', 60);
    }

    public function testTheStatusOfATask(): void
    {
        $task = new ExpectedTask('backup', 60);
        $beat = new DateTimeImmutable('2026-11-08 10:00:00 UTC');
        $now = new DateTimeImmutable('2026-11-08 10:02:00 UTC');

        self::assertEquals(new HeartbeatStatus('backup', HeartbeatState::Late, $beat, new DateTimeImmutable('2026-11-08 10:01:00 UTC')), HeartbeatStatus::of($task, $beat, $now));
        self::assertEquals(new HeartbeatStatus('backup', HeartbeatState::Never, null, null), HeartbeatStatus::of($task, null, $now));
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:01:00 UTC'), HeartbeatStatus::of($task, $beat, $now)->lateSince());
        self::assertNull(HeartbeatStatus::of($task, $beat, $beat)->lateSince());
        self::assertNull(HeartbeatStatus::of($task, null, $now)->lateSince());
    }

    public function testAMaintenanceHasNoNegativeDelay(): void
    {
        $since = new DateTimeImmutable('2026-11-08 10:00:00 UTC');
        $state = new MaintenanceState($since, 0);

        self::assertSame($since, $state->since);
        self::assertSame(0, $state->retryAfterSeconds);

        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIs('Visitors come back in 0 seconds or more, not -1.');

        new MaintenanceState($since, -1);
    }
}
