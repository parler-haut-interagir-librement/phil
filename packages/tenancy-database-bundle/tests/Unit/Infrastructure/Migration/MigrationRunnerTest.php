<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Migration;

use InvalidArgumentException;
use Override;
use Phil\TenancyBundle\Domain\Placement\Placement;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationResult;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationStop;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationTarget;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\MemoryAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Process\Process;

#[CoversClass(MigrationRunner::class)]
#[CoversClass(MigrationResult::class)]
#[CoversClass(MigrationTarget::class)]
#[CoversClass(MigrationStop::class)]
final class MigrationRunnerTest extends TestCase
{
    private string $log;

    private MemoryAvailability $availability;

    #[Override]
    protected function setUp(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'phil-tdb-runner-');
        self::assertIsString($log);
        $this->log = $log;
        $this->availability = new MemoryAvailability();
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->log);
        @unlink($this->log.'.pids');
    }

    public function testEachDatabaseIsMigratedWithItsTenantAndAFailureIsIsolated(): void
    {
        $this->availability->suspendDatabase(Placement::dedicated('tenant_retail'));
        $finished = [];

        $results = $this->runner(4)->run([
            MigrationTarget::shared(),
            $this->target('tenant_retail', 'retail'),
            $this->target('tenant_broken', 'broken'),
        ], finished: static function (MigrationResult $result) use (&$finished): void {
            $finished[] = $result->target->database->toString();
        });

        self::assertCount(3, $results);
        self::assertSame(array_map(static fn (MigrationResult $result): string => $result->target->database->toString(), $results), $finished);
        $byDatabase = array_column(array_map(static fn (MigrationResult $result): array => [$result->target->database->toString(), $result], $results), 1, 0);

        self::assertTrue($byDatabase['shared']->succeeded());
        self::assertSame('migrated shared', $byDatabase['shared']->output);
        self::assertSame('migrated retail', $byDatabase['tenant_retail']->output);
        self::assertFalse($byDatabase['tenant_broken']->succeeded());
        self::assertSame(3, $byDatabase['tenant_broken']->exitCode);
        self::assertSame('The database refused the connection.', $byDatabase['tenant_broken']->output);
        self::assertFalse($byDatabase['tenant_broken']->timedOut);

        self::assertSame(['tenant_broken' => true], $this->availability->databases, 'The failed database is suspended; the one that succeeded is resumed.');
    }

    public function testNoMoreProcessesRunThanTheConcurrency(): void
    {
        $this->runner(1)->run([$this->target('tenant_a', 'a'), $this->target('tenant_b', 'b'), $this->target('tenant_c', 'c')]);

        self::assertSame(['start a', 'end a', 'start b', 'end b', 'start c', 'end c'], $this->logged());
    }

    public function testTheConcurrencyOfARunWinsOverTheConfiguredOne(): void
    {
        $this->runner(1)->run([$this->target('tenant_a', 'wait_a'), $this->target('tenant_b', 'wait_b')], 2);

        // Both start before either ends, in whichever order their processes come up.
        $first = \array_slice($this->logged(), 0, 2);
        sort($first);
        self::assertSame(['start wait_a', 'start wait_b'], $first);
    }

    public function testAMigrationPastItsTimeoutFailsAndIsSuspended(): void
    {
        $runner = new MigrationRunner([__DIR__.'/../../Fixture/migrate.php', $this->log], $this->availability, 2, 0.5, \PHP_BINARY);

        $result = $runner->run([$this->target('tenant_slow', 'slow')])[0];

        self::assertTrue($result->timedOut);
        self::assertNull($result->exitCode);
        self::assertFalse($result->succeeded());
        self::assertTrue($this->availability->isDatabaseSuspended(Placement::dedicated('tenant_slow')));
    }

    public function testOnlyTheEndOfALongOutputIsKept(): void
    {
        $result = $this->runner(1)->run([$this->target('tenant_noisy', 'noisy')])[0];

        self::assertSame(MigrationRunner::OUTPUT_LIMIT + \strlen('…'), \strlen($result->output));
        self::assertStringStartsWith('…x', $result->output);
        self::assertStringEndsWith('xEND', $result->output);
    }

    public function testAnOutputOfTheLimitIsKeptWhole(): void
    {
        $result = $this->runner(1)->run([$this->target('tenant_exact', 'exact')])[0];

        self::assertSame(MigrationRunner::OUTPUT_LIMIT, \strlen($result->output));
        self::assertStringStartsWith('y', $result->output);
        self::assertStringEndsWith('yZ', $result->output);
    }

    public function testTheTwoOutputsOfAProcessAreKeptOnALineEach(): void
    {
        $result = $this->runner(1)->run([$this->target('tenant_both', 'both')])[0];

        self::assertSame("out\nerr", $result->output);
    }

    public function testFourMigrationsRunAtATimeByDefault(): void
    {
        $runner = new MigrationRunner([__DIR__.'/../../Fixture/migrate.php', $this->log], $this->availability, timeout: 30.0, phpBinary: \PHP_BINARY);

        $runner->run(array_map(fn (string $name): MigrationTarget => $this->target('tenant_'.$name, 'wait_'.$name), ['a', 'b', 'c', 'd', 'e']));

        $logged = $this->logged();
        self::assertCount(10, $logged);
        self::assertCount(4, array_filter(\array_slice($logged, 0, 4), static fn (string $line): bool => str_starts_with($line, 'start ')));
        self::assertStringStartsWith('end ', $logged[4], 'The fifth waits for one of the four.');
    }

    public function testTheGivenPhpRunsTheConsole(): void
    {
        $marker = $this->log.'.used';
        $php = $this->log.'.php';
        file_put_contents($php, \sprintf("#!/bin/sh\necho used >> %s\nexec %s \"\$@\"\n", escapeshellarg($marker), escapeshellarg(\PHP_BINARY)));
        chmod($php, 0o755);

        try {
            $runner = new MigrationRunner([__DIR__.'/../../Fixture/migrate.php', $this->log], $this->availability, 1, 30.0, $php);

            self::assertTrue($runner->run([MigrationTarget::shared()])[0]->succeeded());
            self::assertFileExists($marker);
        } finally {
            @unlink($php);
            @unlink($marker);
        }
    }

    public function testNothingToMigrateRunsNothing(): void
    {
        self::assertSame([], $this->runner(1)->run([]));
        self::assertSame([], $this->logged());
    }

    public function testTheRunningPhpIsFoundWhenNoneIsGiven(): void
    {
        $runner = new MigrationRunner([__DIR__.'/../../Fixture/migrate.php', $this->log], $this->availability);

        self::assertTrue($runner->run([MigrationTarget::shared()])[0]->succeeded());
    }

    public function testAStopStopsTheRunningMigrationsWithSigtermAndStartsNoOther(): void
    {
        $this->availability->suspendDatabase(Placement::dedicated('tenant_b'));
        $stop = new MigrationStop();
        $finished = [];
        $started = hrtime(true);

        $results = $this->runner(2, 20.0)->run(
            [$this->target('tenant_a', 'a'), $this->target('tenant_slow', 'slow_one'), $this->target('tenant_b', 'b'), $this->target('tenant_c', 'c')],
            finished: function (MigrationResult $result) use ($stop, &$finished): void {
                $finished[] = $result;

                if ('tenant_a' === $result->target->database->toString()) {
                    $this->waitForPids(1);
                    $stop->request(\SIGTERM);
                }
            },
            stop: $stop,
        );

        self::assertLessThan(10.0, (hrtime(true) - $started) / 1e9, 'A migration that obeys SIGTERM is not given the whole grace period.');
        self::assertSame($results, $finished);
        self::assertSame(['tenant_a', 'tenant_slow', 'tenant_b', 'tenant_c'], array_map(static fn (MigrationResult $result): string => $result->target->database->toString(), $results));
        self::assertTrue($results[0]->succeeded());
        self::assertTrue($results[1]->interrupted);
        self::assertTrue($results[1]->started);
        self::assertNull($results[1]->exitCode);
        self::assertFalse($results[1]->succeeded());
        self::assertFalse($results[1]->timedOut);

        foreach ([$results[2], $results[3]] as $result) {
            self::assertTrue($result->interrupted);
            self::assertFalse($result->started);
            self::assertSame('', $result->output);
        }

        self::assertSame(['tenant_b' => true, 'tenant_slow' => true], $this->availability->databases, 'The stopped database is suspended; those not started are left as they were.');
        self::assertSame(['end a', 'start a', 'start slow_one'], $this->sorted(), 'Nothing starts once the stop is asked for.');
        $this->assertNoneRuns(1);
        self::assertSame(\SIGTERM, $stop->signal());
    }

    public function testAMigrationThatIgnoresSigtermIsKilledAfterTheGracePeriod(): void
    {
        $stop = new MigrationStop();
        $started = hrtime(true);

        $results = $this->runner(2, 0.6)->run([$this->target('tenant_a', 'a'), $this->target('tenant_stubborn', 'stubborn_one')], finished: function (MigrationResult $result) use ($stop): void {
            if ($result->succeeded()) {
                $this->waitForPids(1);
                $stop->request(\SIGINT);
            }
        }, stop: $stop);

        self::assertGreaterThanOrEqual(0.6, (hrtime(true) - $started) / 1e9);
        self::assertTrue($results[1]->interrupted);
        self::assertTrue($this->availability->isDatabaseSuspended(Placement::dedicated('tenant_stubborn')));
        $this->assertNoneRuns(1);
        self::assertSame(\SIGINT, $stop->signal());
    }

    public function testASecondStopKillsWithoutTheGracePeriod(): void
    {
        $stop = new MigrationStop();
        $started = hrtime(true);

        $results = $this->runner(2, 30.0)->run([$this->target('tenant_a', 'a'), $this->target('tenant_stubborn', 'stubborn_one')], finished: function (MigrationResult $result) use ($stop): void {
            if ($result->succeeded()) {
                $this->waitForPids(1);
                $stop->request(\SIGINT);
                $stop->request(\SIGTERM);
            }
        }, stop: $stop);

        self::assertLessThan(10.0, (hrtime(true) - $started) / 1e9);
        self::assertTrue($results[1]->interrupted);
        $this->assertNoneRuns(1);
        self::assertSame(\SIGINT, $stop->signal(), 'The first signal is kept.');
    }

    public function testAMigrationThatEndsWellWhileStoppedSucceeds(): void
    {
        $stop = new MigrationStop();
        $this->availability->suspendDatabase(Placement::dedicated('tenant_graceful'));

        $results = $this->runner(2, 20.0)->run([$this->target('tenant_a', 'a'), $this->target('tenant_graceful', 'graceful_one')], finished: function (MigrationResult $result) use ($stop): void {
            if ('tenant_a' === $result->target->database->toString()) {
                $this->waitForPids(1);
                $stop->request(\SIGTERM);
            }
        }, stop: $stop);

        self::assertTrue($results[1]->succeeded());
        self::assertSame('finished on time', $results[1]->output);
        self::assertSame([], $this->availability->databases);
    }

    public function testAnExceptionStopsTheRunningMigrations(): void
    {
        try {
            $this->runner(2, 20.0)->run([$this->target('tenant_a', 'a'), $this->target('tenant_slow', 'slow_one')], finished: function (): never {
                $this->waitForPids(1);

                throw new RuntimeException('The report could not be written.');
            });
            self::fail('The exception goes on.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The report could not be written.', $runtimeException->getMessage());
        }

        $this->assertNoneRuns(1);
    }

    public function testAStopBeforeTheRunStartsNothing(): void
    {
        $stop = new MigrationStop();
        $stop->request(\SIGTERM);

        $results = $this->runner(2)->run([$this->target('tenant_a', 'a')], stop: $stop);

        self::assertCount(1, $results);
        self::assertFalse($results[0]->started);
        self::assertSame([], $this->logged());
    }

    public function testAStopIsRequestedOnceThenForced(): void
    {
        $stop = new MigrationStop();

        self::assertFalse($stop->isRequested());
        self::assertFalse($stop->isForced());
        self::assertNull($stop->signal());

        $stop->request(\SIGTERM);
        self::assertTrue($stop->isRequested());
        self::assertFalse($stop->isForced());

        $stop->request(\SIGINT);
        self::assertTrue($stop->isForced());
        self::assertSame(\SIGTERM, $stop->signal());
    }

    public function testAResultSucceedsOnlyWithExitCodeZeroInTime(): void
    {
        $target = MigrationTarget::shared();

        self::assertTrue(new MigrationResult($target, 0)->succeeded());
        self::assertFalse(new MigrationResult($target, 1)->succeeded());
        self::assertFalse(new MigrationResult($target, null)->succeeded());
        self::assertFalse(new MigrationResult($target, 0, timedOut: true)->succeeded());
        self::assertFalse(new MigrationResult($target, 0, interrupted: true)->succeeded());
        self::assertEquals(new MigrationResult($target, null, 'half', interrupted: true), MigrationResult::interrupted($target, 'half'));
        self::assertEquals(new MigrationResult($target, null, interrupted: true), MigrationResult::interrupted($target));
        self::assertEquals(new MigrationResult($target, null, interrupted: true, started: false), MigrationResult::notStarted($target));
        self::assertNull($target->tenant);
        self::assertTrue($target->database->isShared());
    }

    /**
     * @return iterable<string, array{0: list<string>, 1: int, 2: ?float, 3: string, 4?: float}>
     */
    public static function refused(): iterable
    {
        yield 'no command' => [[], 1, null, 'The migration command cannot be empty.'];
        yield 'no concurrency' => [['console'], 0, null, 'The concurrency of the migrations is between 1 and 32; 0 was given.'];
        yield 'too much concurrency' => [['console'], 33, null, 'The concurrency of the migrations is between 1 and 32; 33 was given.'];
        yield 'no time' => [['console'], 1, 0.0, 'The timeout of a migration is a positive number of seconds, or null.'];
        yield 'a negative grace period' => [['console'], 1, null, 'The grace period of a stopped migration is a number of seconds, 0 or more.', -0.1];
    }

    /**
     * @param list<string> $command
     */
    #[DataProvider('refused')]
    public function testAnInvalidSettingIsRefused(array $command, int $concurrency, ?float $timeout, string $message, float $gracePeriod = 10.0): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        new MigrationRunner($command, $this->availability, $concurrency, $timeout, null, $gracePeriod);
    }

    public function testTheLimitsOfTheConcurrencyAreAccepted(): void
    {
        $runner = new MigrationRunner(['console'], $this->availability, MigrationRunner::MAX_CONCURRENCY, null, null, 0.0);

        self::assertSame([], $runner->run([], 1));
    }

    public function testAnInvalidConcurrencyOfARunIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The concurrency of the migrations is between 1 and 32; 33 was given.');

        $this->runner(1)->run([], 33);
    }

    private function runner(int $concurrency, float $gracePeriod = 10.0): MigrationRunner
    {
        return new MigrationRunner([__DIR__.'/../../Fixture/migrate.php', $this->log], $this->availability, $concurrency, 30.0, \PHP_BINARY, $gracePeriod);
    }

    /**
     * Waits until this many slow migrations have written their process ids.
     */
    private function waitForPids(int $count): void
    {
        for ($i = 0; $i < 500 && \count($this->pids()) < $count; ++$i) {
            usleep(10_000);
        }

        self::assertCount($count, $this->pids());
    }

    private function assertNoneRuns(int $count): void
    {
        $pids = $this->pids();
        self::assertCount($count, $pids);

        foreach ($pids as $pid) {
            self::assertSame(1, new Process(['sh', '-c', 'kill -0 "$1" 2>/dev/null', 'sh', (string) $pid])->run(), \sprintf('The migration %d no longer runs.', $pid));
        }
    }

    /**
     * @return list<int>
     */
    private function pids(): array
    {
        $pids = is_file($this->log.'.pids') ? (string) file_get_contents($this->log.'.pids') : '';

        return array_values(array_map(intval(...), array_filter(explode("\n", $pids), static fn (string $line): bool => '' !== $line)));
    }

    /**
     * @return list<string>
     */
    private function sorted(): array
    {
        $logged = $this->logged();
        sort($logged);

        return $logged;
    }

    private function target(string $database, string $tenant): MigrationTarget
    {
        return new MigrationTarget(Placement::dedicated($database), TenantId::fromString($tenant));
    }

    /**
     * @return list<string>
     */
    private function logged(): array
    {
        return array_values(array_filter(explode("\n", (string) file_get_contents($this->log)), static fn (string $line): bool => '' !== $line));
    }
}
