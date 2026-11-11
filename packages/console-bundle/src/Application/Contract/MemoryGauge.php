<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Application\Contract;

/**
 * The memory of the process, in bytes.
 */
interface MemoryGauge
{
    public function usage(): int;

    public function peak(): int;
}
