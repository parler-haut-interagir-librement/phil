<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Unit\Fixture;

use Override;
use Random\Engine;

/**
 * An engine that always draws the same 64 bits, so that `Randomizer::nextFloat()` gives a known value.
 */
final readonly class FixedEngine implements Engine
{
    public function __construct(
        private int $value,
    ) {
    }

    #[Override]
    public function generate(): string
    {
        return pack('P', $this->value);
    }
}
