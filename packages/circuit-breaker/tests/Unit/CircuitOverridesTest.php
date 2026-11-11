<?php

declare(strict_types=1);

namespace Phil\CircuitBreaker\Tests\Unit;

use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\CircuitOverrides;
use Phil\CircuitBreaker\Tests\Unit\Fixture\SwitchableOverride;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CircuitOverrides::class)]
final class CircuitOverridesTest extends TestCase
{
    public function testWithoutAnOverrideNoCircuitIsForcedOpen(): void
    {
        self::assertFalse(new CircuitOverrides()->isForcedOpen(CircuitKey::of('http.payments')));
    }

    public function testOneOverrideThatForcesTheCircuitIsEnough(): void
    {
        $forcing = new SwitchableOverride();
        $forcing->forcedOpen = true;

        self::assertTrue(new CircuitOverrides([new SwitchableOverride(), $forcing])->isForcedOpen(CircuitKey::of('http.payments')));
        self::assertFalse(new CircuitOverrides([new SwitchableOverride(), new SwitchableOverride()])->isForcedOpen(CircuitKey::of('http.payments')));
    }

    /**
     * The first override that forces the circuit ends the round: the ones after it are not asked.
     */
    public function testTheOverridesAfterTheFirstThatForcesAreNotAsked(): void
    {
        $forcing = new SwitchableOverride();
        $forcing->forcedOpen = true;

        $after = new SwitchableOverride();

        self::assertTrue(new CircuitOverrides([$forcing, $after])->isForcedOpen(CircuitKey::of('http.payments')));
        self::assertSame(1, $forcing->asked);
        self::assertSame(0, $after->asked);
    }
}
