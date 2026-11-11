<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Unit\Fixture;

use Override;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Contract\CircuitOverride;

final class SwitchableOverride implements CircuitOverride
{
    public bool $forcedOpen = false;

    public int $asked = 0;

    #[Override]
    public function isForcedOpen(CircuitKey $key): bool
    {
        ++$this->asked;

        return $this->forcedOpen;
    }
}
