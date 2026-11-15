<?php

declare(strict_types=1);

namespace Phil\Audio\Ffmpeg;

use Override;
use Phil\Audio\CommandResult;
use Phil\Audio\Contract\CommandRunner;
use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\Audio\Exception\LimitExceeded;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs a program through Symfony Process from the list of its arguments: PHP starts it without a shell, and where
 * Symfony Process falls back on one, each argument is quoted, so that no file name is ever read as a command. The run is
 * stopped at its timeout, or as soon as it has printed more than it may.
 */
final readonly class ProcessCommandRunner implements CommandRunner
{
    #[Override]
    public function run(array $command, float $timeout, int $maxOutputBytes): CommandResult
    {
        $process = new Process($command, timeout: $timeout);
        $size = 0;

        try {
            $process->start();
            $exceeded = $process->waitUntil(static function (string $type, string $output) use (&$size, $maxOutputBytes): bool {
                $size += \strlen($output);

                return $size > $maxOutputBytes;
            });

            if ($exceeded) {
                $process->stop(0);

                throw new LimitExceeded(\sprintf('"%s" printed more than %d bytes and was stopped.', $command[0], $maxOutputBytes));
            }

            $process->wait();
        } catch (ProcessTimedOutException $exception) {
            throw new LimitExceeded(\sprintf('"%s" ran longer than %s seconds and was stopped.', $command[0], $timeout), 0, $exception);
        } catch (RuntimeException $exception) {
            throw new AudioToolUnavailable(\sprintf('"%s" cannot be started: %s', $command[0], $exception->getMessage()), 0, $exception);
        }

        return new CommandResult($process->getExitCode() ?? -1, $process->getOutput(), $process->getErrorOutput());
    }
}
