<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Fixture;

use Override;
use Phil\Backup\Database\CommandRunner;
use Phil\Backup\Exception\BackupFailed;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the real tools with Symfony Process, without a shell: what an application's runner does, for the tests that
 * use the real `sqlite3`.
 */
final class ProcessRunner implements CommandRunner
{
    #[Override]
    public function find(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $path = new ExecutableFinder()->find($candidate);

            if (null !== $path) {
                return $path;
            }
        }

        throw BackupFailed::missing($candidates);
    }

    #[Override]
    public function run(array $command, array $environment = [], ?string $input = null): string
    {
        $process = new Process($command, null, $environment);
        $process->setInput(null === $input ? null : (string) file_get_contents($input));
        $process->run();

        if (!$process->isSuccessful()) {
            throw BackupFailed::failed(basename($command[0]), $process->getErrorOutput());
        }

        return $process->getOutput();
    }
}
