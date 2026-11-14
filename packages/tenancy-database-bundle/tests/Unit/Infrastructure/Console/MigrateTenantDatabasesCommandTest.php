<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Unit\Infrastructure\Console;

use InvalidArgumentException;
use Override;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrateTenantDatabasesCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrationTargets;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use Phil\TenancyDatabaseBundle\Infrastructure\Placement\ConfiguredPlacements;
use Phil\TenancyDatabaseBundle\Tests\Unit\Fixture\MemoryAvailability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(MigrateTenantDatabasesCommand::class)]
final class MigrateTenantDatabasesCommandTest extends TestCase
{
    private string $log;

    private MemoryAvailability $availability;

    #[Override]
    protected function setUp(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'phil-tdb-command-');
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

    public function testEveryDatabaseIsMigratedAndAFailureIsReported(): void
    {
        $tester = $this->tester(['wholesale' => 'tenant_wholesale', 'broken' => 'tenant_broken']);

        self::assertSame(1, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertStringContainsString('✓ shared', $display);
        self::assertStringContainsString('✓ tenant_wholesale', $display);
        self::assertStringContainsString("✗ tenant_broken failed with exit code 3; its tenants are suspended.\nThe database refused the connection.", $display);
        self::assertStringContainsString('2 of 3 databases migrated.', $display);
    }

    public function testTheCommandSucceedsWhenEveryDatabaseIsMigrated(): void
    {
        $tester = $this->tester(['wholesale' => 'tenant_wholesale']);

        self::assertSame(0, $tester->execute(['--only' => ['tenant_wholesale'], '--concurrency' => '1']));
        self::assertSame("✓ tenant_wholesale\n1 of 1 databases migrated.\n", $tester->getDisplay());
    }

    public function testAMigrationPastItsTimeoutIsReported(): void
    {
        $command = new MigrateTenantDatabasesCommand(new MigrationTargets(new ConfiguredPlacements(['slow' => 'tenant_slow'])), new MigrationRunner([__DIR__.'/../../Fixture/migrate.php', $this->log], $this->availability, 2, 0.5, \PHP_BINARY));
        $tester = new CommandTester($command);

        self::assertSame(1, $tester->execute(['--only' => ['tenant_slow']]));
        self::assertStringContainsString('✗ tenant_slow ran past its timeout; its tenants are suspended.', $tester->getDisplay());
    }

    public function testASignalDuringTheRunStopsTheMigrationsAndListsTheDatabasesNotMigrated(): void
    {
        $command = $this->command(['slow_one' => 'tenant_slow', 'later' => 'tenant_later']);
        $answers = [];
        pcntl_async_signals(true);
        pcntl_signal(\SIGALRM, static function () use ($command, &$answers): void {
            $answers[] = $command->handleSignal(\SIGTERM, 143);
        });
        pcntl_alarm(1);

        try {
            $tester = new CommandTester($command);
            $status = $tester->execute(['--only' => ['tenant_slow', 'tenant_later'], '--concurrency' => '1']);
        } finally {
            pcntl_alarm(0);
            pcntl_signal(\SIGALRM, \SIG_DFL);
        }

        self::assertSame([false], $answers, 'The command carries on to wait for the migrations and report.');
        self::assertSame(143, $status);
        self::assertSame(<<<'TEXT'
            ✗ tenant_slow was stopped before it finished; its tenants are suspended.
            - tenant_later was not started.
            0 of 2 databases migrated.
            Stopped by signal 15; not migrated: tenant_slow, tenant_later.

            TEXT, $tester->getDisplay());
        self::assertSame(['tenant_slow' => true], $this->availability->databases);
        self::assertSame(143, $command->handleSignal(\SIGTERM, 143), 'Once the run is over, the signal is left to the console.');
    }

    public function testTheCommandListensToSigintAndSigterm(): void
    {
        $command = $this->command([]);

        self::assertSame([\SIGINT, \SIGTERM], $command->getSubscribedSignals());
        self::assertSame(130, $command->handleSignal(\SIGINT, 130), 'Before a run, the signal is left to the console.');
        self::assertFalse($command->handleSignal(\SIGINT, false));
        self::assertSame(0, $command->handleSignal(\SIGINT));
    }

    public function testARunThatFailsLeavesTheSignalsToTheConsole(): void
    {
        $command = $this->command([]);

        try {
            new CommandTester($command)->execute(['--concurrency' => 'two']);
            self::fail('The concurrency is refused.');
        } catch (InvalidArgumentException) {
            self::assertSame(143, $command->handleSignal(\SIGTERM, 143));
        }
    }

    public function testAConcurrencyThatIsNotANumberIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('The concurrency of the migrations is between 1 and 32; 0 was given.');

        $this->tester([])->execute(['--concurrency' => 'two']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notNumbers(): iterable
    {
        yield 'a number and a word' => ['2x'];
        yield 'a number and a line break' => ["2\n"];
        yield 'a number of five digits' => ['12345'];
        yield 'zero' => ['0'];
    }

    #[DataProvider('notNumbers')]
    public function testAConcurrencyThatIsNotExactlyANumberIsRefused(string $concurrency): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->tester([])->execute(['--concurrency' => $concurrency]);
    }

    public function testAConcurrencyOfLeadingZerosIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->tester([])->execute(['--concurrency' => '02']);
    }

    /**
     * @param array<string, string> $dedicated
     */
    private function tester(array $dedicated): CommandTester
    {
        return new CommandTester($this->command($dedicated));
    }

    /**
     * @param array<string, string> $dedicated
     */
    private function command(array $dedicated): MigrateTenantDatabasesCommand
    {
        return new MigrateTenantDatabasesCommand(new MigrationTargets(new ConfiguredPlacements($dedicated)), new MigrationRunner([__DIR__.'/../../Fixture/migrate.php', $this->log], $this->availability, 4, 30.0, \PHP_BINARY));
    }
}
