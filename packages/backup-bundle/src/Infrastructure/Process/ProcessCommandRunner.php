<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Infrastructure\Process;

use Override;
use Phil\Backup\Database\CommandRunner;
use Phil\Backup\Exception\BackupFailed;
use Phil\BackupBundle\Application\Event\BackupToolRunningLong;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Runs the client tools with Symfony Process: no shell, each argument passed as it is, no timeout by default — a
 * backup of a large database takes the time it takes.
 *
 * A tool still running after `$warnAfter` seconds (zero or null, never) is logged as a warning, once, and announced by a
 * `BackupToolRunningLong` event, so that monitoring can tell a slow backup from a stuck one. A tool still running
 * after `$timeout` seconds (zero or null, no limit) is sent SIGTERM, then SIGKILL if it has not stopped within `$gracePeriod` seconds, and
 * the backup fails.
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
        private ?float $warnAfter = null,
        private float $gracePeriod = 10.0,
        private ?LoggerInterface $logger = null,
        private ?EventDispatcherInterface $dispatcher = null,
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
        $process = new WatchedProcess($command, null, $environment, null, null);

        if (null !== $input) {
            $stream = @fopen($input, 'r');

            if (false === $stream) {
                throw BackupFailed::because(\sprintf('The backup %s cannot be read.', $input));
            }

            $process->setInput($stream);
        }

        $tool = basename($command[0]);
        // Zero, as for Symfony Process, is no limit; and no warning.
        $timeout = null !== $this->timeout && $this->timeout > 0.0 ? $this->timeout : null;
        $warnAfter = null !== $this->warnAfter && $this->warnAfter > 0.0 ? $this->warnAfter : null;
        $started = hrtime(true);
        $warned = false;
        $process->watch(function (WatchedProcess $process) use ($tool, $timeout, $warnAfter, $started, &$warned): void {
            $elapsed = (hrtime(true) - $started) / 1e9;

            if (null !== $timeout && $elapsed >= $timeout) {
                $process->stop($this->gracePeriod);

                throw BackupFailed::because(\sprintf('%s was still running after %s seconds, the limit of phil_backup.timeout, and was stopped.', $tool, $timeout));
            }

            if (!$warned && null !== $warnAfter && $elapsed >= $warnAfter) {
                $warned = true;
                $this->logger?->warning('The backup tool {tool} is still running after {seconds} seconds.', ['tool' => $tool, 'pid' => $process->getPid(), 'seconds' => $warnAfter]);
                $this->dispatcher?->dispatch(new BackupToolRunningLong($tool, $process->getPid(), $warnAfter));
            }
        });
        $process->run();

        if (!$process->isSuccessful()) {
            throw BackupFailed::failed($tool, $process->getErrorOutput());
        }

        return $process->getOutput();
    }
}
