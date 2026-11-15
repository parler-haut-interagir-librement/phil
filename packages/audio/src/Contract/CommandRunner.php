<?php

declare(strict_types=1);

namespace Phil\Audio\Contract;

use Phil\Audio\CommandResult;
use Phil\Audio\Exception\AudioToolUnavailable;
use Phil\Audio\Exception\LimitExceeded;

/**
 * Runs an external program from its arguments, which no shell ever reads, within a time and an output it may not pass.
 */
interface CommandRunner
{
    /**
     * @param non-empty-list<string> $command        the program, then each of its arguments
     * @param float                  $timeout        the seconds it may run
     * @param int                    $maxOutputBytes the bytes it may write, standard output and errors together
     *
     * @throws AudioToolUnavailable when the program cannot be started
     * @throws LimitExceeded        when it runs too long or writes too much, and is then stopped
     */
    public function run(array $command, float $timeout, int $maxOutputBytes): CommandResult;
}
