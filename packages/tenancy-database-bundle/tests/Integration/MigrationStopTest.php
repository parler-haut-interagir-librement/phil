<?php

declare(strict_types=1);

namespace Phil\TenancyDatabaseBundle\Tests\Integration;

use Override;
use Phil\TenancyDatabaseBundle\Infrastructure\Console\MigrateTenantDatabasesCommand;
use Phil\TenancyDatabaseBundle\Infrastructure\Migration\MigrationRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * The migration command in a console of its own, stopped while its migrations run: no migration outlives it.
 */
#[CoversClass(MigrateTenantDatabasesCommand::class)]
#[CoversClass(MigrationRunner::class)]
final class MigrationStopTest extends TestCase
{
    private const string TENANTS = 'slow_a=tenant_slow_a,slow_b=tenant_slow_b,stubborn_c=tenant_stubborn_c,later=tenant_later';

    private string $log;

    #[Override]
    protected function setUp(): void
    {
        $log = tempnam(sys_get_temp_dir(), 'phil-tdb-stop-');
        self::assertIsString($log);
        $this->log = $log;
    }

    #[Override]
    protected function tearDown(): void
    {
        @unlink($this->log);
        @unlink($this->log.'.pids');
    }

    public function testSigtermStopsEveryMigrationAndTheCommandReportsWhatWasNotMigrated(): void
    {
        $console = $this->console(['--only=tenant_slow_a', '--only=tenant_slow_b', '--only=tenant_stubborn_c', '--only=tenant_later', '--concurrency=3']);
        $console->start();
        $this->waitForPids($console, 3);

        $console->signal(\SIGTERM);
        $console->wait();

        self::assertSame(143, $console->getExitCode(), $console->getErrorOutput());
        $output = $console->getOutput();
        self::assertStringContainsString('✗ tenant_slow_a was stopped before it finished; its tenants are suspended.', $output);
        self::assertStringContainsString('✗ tenant_slow_b was stopped before it finished; its tenants are suspended.', $output);
        self::assertStringContainsString('✗ tenant_stubborn_c was stopped before it finished; its tenants are suspended.', $output);
        self::assertStringContainsString('- tenant_later was not started.', $output);
        self::assertStringContainsString('0 of 4 databases migrated.', $output);
        self::assertStringContainsString('Stopped by signal 15; not migrated: ', $output);
        $this->assertNoMigrationRuns();
    }

    public function testSigintStopsEveryMigrationToo(): void
    {
        $console = $this->console(['--only=tenant_slow_a', '--only=tenant_slow_b', '--concurrency=2']);
        $console->start();
        $this->waitForPids($console, 2);

        $console->signal(\SIGINT);
        $console->wait();

        self::assertSame(130, $console->getExitCode(), $console->getErrorOutput());
        $this->assertNoMigrationRuns();
    }

    public function testAFatalErrorStopsEveryMigration(): void
    {
        $console = $this->console(['--only=tenant_slow_a', '--only=tenant_slow_b', '--only=tenant_wait_d', '--concurrency=3'], 'slow_a=tenant_slow_a,slow_b=tenant_slow_b,wait_d=tenant_wait_d', ['PHIL_TDB_FATAL' => '1']);
        $console->start();
        $this->waitForPids($console, 2);

        $console->wait();

        self::assertSame(255, $console->getExitCode());
        self::assertStringContainsString('Allowed memory size', $console->getOutput().$console->getErrorOutput());
        $this->assertNoMigrationRuns();
    }

    /**
     * @param list<string>          $arguments
     * @param array<string, string> $environment
     */
    private function console(array $arguments, string $tenants = self::TENANTS, array $environment = []): Process
    {
        return new Process([\PHP_BINARY, __DIR__.'/App/stoppable-console.php', $this->log, $tenants, 'phil:tenancy:database:migrate', ...$arguments], null, $environment, null, 30.0);
    }

    private function waitForPids(Process $console, int $count): void
    {
        for ($i = 0; $i < 1000 && \count($this->pids()) < $count && $console->isRunning(); ++$i) {
            usleep(10_000);
        }

        self::assertCount($count, $this->pids(), $console->getOutput().$console->getErrorOutput());
    }

    private function assertNoMigrationRuns(): void
    {
        foreach ($this->pids() as $pid) {
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
}
