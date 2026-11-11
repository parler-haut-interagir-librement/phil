<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit;

use Phil\Backup\Exception\BackupFailed;
use Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner;
use Phil\BackupBundle\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * The runner on real processes.
 */
#[CoversClass(ProcessCommandRunner::class)]
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

    public function testATimeoutStopsATool(): void
    {
        $this->expectException(ProcessTimedOutException::class);

        new ProcessCommandRunner([], 0.2)->run(['sleep', '2']);
    }
}
