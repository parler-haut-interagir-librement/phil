<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Infrastructure\Runtime;

use Override;
use Phil\ConsoleBundle\Application\Contract\MemoryGauge;

/**
 * The memory PHP reports: what the scripts use, and the process's peak since it started.
 */
final readonly class PhpMemoryGauge implements MemoryGauge
{
    #[Override]
    public function usage(): int
    {
        return memory_get_usage();
    }

    #[Override]
    public function peak(): int
    {
        return memory_get_peak_usage();
    }
}
