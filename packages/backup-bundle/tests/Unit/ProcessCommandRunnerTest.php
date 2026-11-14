<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit;

use Phil\Backup\Exception\BackupFailed;
use Phil\BackupBundle\Application\Event\BackupToolRunningLong;
use Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner;
use Phil\BackupBundle\Infrastructure\Process\WatchedProcess;
use Phil\BackupBundle\Tests\Unit\Fixture\Files;
use Phil\BackupBundle\Tests\Unit\Fixture\RecordingDispatcher;
use Phil\BackupBundle\Tests\Unit\Fixture\RecordingLogger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * The runner on real processes.
 */
#[CoversClass(ProcessCommandRunner::class)]
#[CoversClass(WatchedProcess::class)]
#[CoversClass(BackupToolRunningLong::class)]
final class ProcessCommandRunnerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = Files::directory();
    }

    protected function tearDown(): void
    {
        Files::remove($this->directory);
    }

    public function testTheRunnerFindsAToolOnThePathOrWhereTheConfigurationSays(): void
    {
        self::assertSame('/opt/sqlite/bin/sqlite3', new ProcessCommandRunner(['sqlite3' => '/opt/sqlite/bin/sqlite3'])->find(['sqlite3']));
        self::assertStringEndsWith('/sh', new ProcessCommandRunner()->find(['phil-backup-missing-tool', 'sh']));

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains("phil-backup-missing-tool is not installed or not on the PATH: install your database's client tools, or give its path in phil_backup.binaries.");
        new ProcessCommandRunner()->find(['phil-backup-missing-tool']);
    }

    public function testTheRunnerGivesTheEnvironmentAndTheInputFileAndReturnsTheOutput(): void
    {
        $input = $this->directory.'/input.txt';
        file_put_contents($input, 'from the file');
        $runner = new ProcessCommandRunner();

        self::assertSame('from the file and the environment', $runner->run(['sh', '-c', 'cat; printf %s "$PHIL_TEST"'], ['PHIL_TEST' => ' and the environment'], $input));

        try {
            $runner->run(['/bin/sh', '-c', 'echo "no such role" >&2; exit 3']);
            self::fail('A failure is reported.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('sh failed: no such role', $backupFailed->getMessage());
        }

        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The backup %s/missing.sql cannot be read.', $this->directory));
        $runner->run(['sh', '-c', 'true'], [], $this->directory.'/missing.sql');
    }

    public function testATimeoutStopsAToolWithSigtermAndFailsTheBackup(): void
    {
        $started = hrtime(true);

        try {
            new ProcessCommandRunner([], 0.2)->run(['sleep', '5']);
            self::fail('The tool is stopped.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('sleep was still running after 0.2 seconds, the limit of phil_backup.timeout, and was stopped.', $backupFailed->getMessage());
        }

        self::assertLessThan(3.0, (hrtime(true) - $started) / 1e9, 'SIGTERM stops a tool that obeys it without waiting for the grace period.');
    }

    public function testAToolThatIgnoresSigtermIsKilledAfterTheGracePeriod(): void
    {
        $pidFile = $this->directory.'/pid';
        $started = hrtime(true);

        try {
            new ProcessCommandRunner([], 0.2, null, 0.6)->run(['sh', '-c', 'trap "" TERM; echo $$ > "$1"; while :; do sleep 0.05; done', 'sh', $pidFile]);
            self::fail('The tool is killed.');
        } catch (BackupFailed $backupFailed) {
            self::assertStringStartsWith('sh was still running after 0.2 seconds', $backupFailed->getMessage());
        }

        $elapsed = (hrtime(true) - $started) / 1e9;
        self::assertGreaterThanOrEqual(0.8, $elapsed, 'The tool is given its grace period after SIGTERM.');
        self::assertLessThan(5.0, $elapsed, 'SIGKILL follows the grace period, not the default ten seconds.');
        $pid = (int) file_get_contents($pidFile);
        self::assertGreaterThan(0, $pid);
        self::assertSame(1, new Process(['ps', '-p', (string) $pid])->run(), 'The tool no longer runs.');
    }

    public function testAZeroOrMissingTimeoutIsNoLimit(): void
    {
        self::assertSame('done', new ProcessCommandRunner([], 0.0)->run(['sh', '-c', 'sleep 0.3; printf done']));
        self::assertSame('done', new ProcessCommandRunner([], null, 0.1)->run(['sh', '-c', 'sleep 0.3; printf done']), 'Without a logger or a dispatcher, the warning goes nowhere.');
    }

    public function testAToolStillRunningAfterWarnAfterIsLoggedAndAnnouncedOnce(): void
    {
        $logger = new RecordingLogger();
        $dispatcher = new RecordingDispatcher();

        self::assertSame('ok', new ProcessCommandRunner([], null, 0.1, 10.0, $logger, $dispatcher)->run(['sh', '-c', 'sleep 0.8; printf ok']));

        self::assertCount(1, $logger->records);
        [$level, $message, $context] = $logger->records[0];
        self::assertSame(LogLevel::WARNING, $level);
        self::assertSame('The backup tool {tool} is still running after {seconds} seconds.', $message);
        self::assertSame(['tool', 'pid', 'seconds'], array_keys($context));
        self::assertSame('sh', $context['tool']);
        self::assertSame(0.1, $context['seconds']);
        self::assertIsInt($context['pid']);
        self::assertGreaterThan(0, $context['pid']);
        self::assertEquals([new BackupToolRunningLong('sh', $context['pid'], 0.1)], $dispatcher->events);
    }

    public function testAQuickToolIsNotWarnedAboutNorAnyToolWithoutWarnAfter(): void
    {
        $logger = new RecordingLogger();
        $dispatcher = new RecordingDispatcher();

        new ProcessCommandRunner([], null, 5.0, 10.0, $logger, $dispatcher)->run(['sh', '-c', 'sleep 0.5']);
        new ProcessCommandRunner([], null, null, 10.0, $logger, $dispatcher)->run(['sh', '-c', 'sleep 0.5']);
        new ProcessCommandRunner([], null, 0.0, 10.0, $logger, $dispatcher)->run(['sh', '-c', 'sleep 0.5']);

        self::assertSame([], $logger->records);
        self::assertSame([], $dispatcher->events);
    }

    public function testATimeoutAfterTheWarning(): void
    {
        $logger = new RecordingLogger();
        $dispatcher = new RecordingDispatcher();

        try {
            new ProcessCommandRunner([], 0.6, 0.1, 10.0, $logger, $dispatcher)->run(['sleep', '5']);
            self::fail('The tool is stopped.');
        } catch (BackupFailed $backupFailed) {
            self::assertSame('sleep was still running after 0.6 seconds, the limit of phil_backup.timeout, and was stopped.', $backupFailed->getMessage());
        }

        self::assertCount(1, $logger->records);
        self::assertCount(1, $dispatcher->events);
    }

    public function testTheWatchedProcessKeepsItsOwnTimeout(): void
    {
        self::assertSame(0, new WatchedProcess(['true'])->run(), 'Without a watcher, it runs as a process.');

        $this->expectException(ProcessTimedOutException::class);

        new WatchedProcess(['sleep', '2'], null, null, null, 0.2)->run();
    }
}
