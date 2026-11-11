<?php

declare(strict_types=1);

namespace Phil\Enums\Tests\Fixture;

use Override;
use Random\Engine;

/**
 * An engine that hands out the numbers it is given: `Randomizer::getInt(1, $n)` then draws `1 + $number` for each
 * number below `$n`, so that a test chooses the draw.
 */
final class SequenceEngine implements Engine
{
    /**
     * @param list<int> $numbers
     */
    public function __construct(
        private array $numbers,
    ) {
    }

    #[Override]
    public function generate(): string
    {
        return pack('V', array_shift($this->numbers) ?? 0);
    }
}
