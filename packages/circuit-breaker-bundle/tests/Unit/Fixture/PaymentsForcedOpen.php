<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Tests\Unit\Fixture;

use Override;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Contract\CircuitOverride;

/**
 * An override of the application, tagged by autoconfiguration: it forces the circuit `http.payments` open.
 */
final class PaymentsForcedOpen implements CircuitOverride
{
    #[Override]
    public function isForcedOpen(CircuitKey $key): bool
    {
        return 'http.payments' === $key->value;
    }
}
