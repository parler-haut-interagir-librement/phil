<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Console;

use DateTimeImmutable;
use Phil\ConsoleBundle\Application\Contract\DebugSwitch;

/**
 * What the bundle keeps of one running command until it ends.
 *
 * @internal
 */
final readonly class Run
{
    /**
     * @param list<resource>   $filters
     * @param DebugSwitch|null $debug   The switch to turn off at the end, when the run enabled it
     */
    public function __construct(
        public string $command,
        public DateTimeImmutable $start,
        public int $memory,
        public array $filters,
        public ?DebugSwitch $debug,
    ) {
    }
}
