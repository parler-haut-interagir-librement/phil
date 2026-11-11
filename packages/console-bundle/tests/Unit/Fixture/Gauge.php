<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Fixture;

use Override;
use Phil\ConsoleBundle\Application\Contract\MemoryGauge;

/**
 * A memory that reads the values it was given, one per call, the last one repeated.
 */
final class Gauge implements MemoryGauge
{
    /**
     * @param list<int> $usages
     */
    public function __construct(
        private array $usages = [1_000_000],
        private readonly int $peak = 4 * 1024 * 1024,
    ) {
    }

    #[Override]
    public function usage(): int
    {
        return \count($this->usages) > 1 ? array_shift($this->usages) : $this->usages[0];
    }

    #[Override]
    public function peak(): int
    {
        return $this->peak;
    }
}
