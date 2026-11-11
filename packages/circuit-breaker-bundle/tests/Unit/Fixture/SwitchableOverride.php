<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Tests\Unit\Fixture;

use Override;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Contract\CircuitOverride;

final class SwitchableOverride implements CircuitOverride
{
    public bool $forcedOpen = false;

    #[Override]
    public function isForcedOpen(CircuitKey $key): bool
    {
        return $this->forcedOpen;
    }
}
