<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Application\Contract;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * What `--debug-phil` turns on: the debug logs of the named channels written to the command's output, until the
 * command ends.
 */
interface DebugSwitch
{
    /**
     * @return list<string> The channels the switch knows, in order
     */
    public function channels(): array;

    /**
     * Writes the logs of the channels, from the debug level up, to the output; a channel it does not know is skipped.
     *
     * @param list<string> $channels
     */
    public function enable(array $channels, OutputInterface $output): void;

    /**
     * Stops what enable() started; does nothing when nothing is enabled.
     */
    public function disable(): void;
}
