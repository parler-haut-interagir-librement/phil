<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Process;

use Override;
use Phil\Backup\Database\CommandRunner;
use Phil\Backup\Exception\BackupFailed;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the client tools with Symfony Process: no shell, each argument passed as it is, no timeout by default — a
 * backup of a large database takes the time it takes.
 *
 * A tool is found by the path the configuration gives it (`phil_backup.binaries`), or on the `PATH`.
 */
final readonly class ProcessCommandRunner implements CommandRunner
{
    /**
     * @param array<string, string> $binaries tool name to path
     */
    public function __construct(
        private array $binaries = [],
        private ?float $timeout = null,
    ) {
    }

    #[Override]
    public function find(array $candidates): string
    {
        $finder = new ExecutableFinder();

        foreach ($candidates as $candidate) {
            $path = $this->binaries[$candidate] ?? $finder->find($candidate);

            if (null !== $path) {
                return $path;
            }
        }

        throw BackupFailed::missing($candidates, 'give its path in phil_backup.binaries');
    }

    #[Override]
    public function run(array $command, array $environment = [], ?string $input = null): string
    {
        $process = new Process($command, null, $environment, null, $this->timeout);

        if (null !== $input) {
            $stream = @fopen($input, 'r');

            if (false === $stream) {
                throw BackupFailed::because(\sprintf('The backup %s cannot be read.', $input));
            }

            $process->setInput($stream);
        }

        $process->run();

        if (!$process->isSuccessful()) {
            throw BackupFailed::failed(basename($command[0]), $process->getErrorOutput());
        }

        return $process->getOutput();
    }
}
