<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Application\Circuit;

use Phil\CircuitBreaker\CircuitKey;
use Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride;
use Phil\FeatureFlagsBundle\Application\Flag\AnonymousContextResolver;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Infrastructure\Configuration\ConfiguredFlagProvider;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\InMemoryFlagStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FlagCircuitOverride::class)]
#[CoversClass(AnonymousContextResolver::class)]
final class FlagCircuitOverrideTest extends TestCase
{
    public function testACircuitIsForcedOpenWhileItsFlagIsOn(): void
    {
        $override = $this->override(['circuit-open.http.payments' => true]);

        self::assertTrue($override->isForcedOpen(CircuitKey::of('http.payments')));
    }

    public function testACircuitWhoseFlagIsOffIsNotForcedOpen(): void
    {
        $override = $this->override(['circuit-open.http.payments' => false]);

        self::assertFalse($override->isForcedOpen(CircuitKey::of('http.payments')));
    }

    public function testACircuitWhoseFlagIsUndeclaredIsNotForcedOpen(): void
    {
        self::assertFalse($this->override([])->isForcedOpen(CircuitKey::of('http.geo')));
    }

    /**
     * Forcing one circuit open reaches its own key only.
     */
    public function testAForcedCircuitStaysConfinedToItsKey(): void
    {
        $override = $this->override(['circuit-open.http.payments' => true]);

        self::assertFalse($override->isForcedOpen(CircuitKey::of('slack-webhook')));
    }

    public function testAKeyNamesTheFlagThatForcesItsCircuitOpen(): void
    {
        self::assertSame('circuit-open.http.payments', FlagCircuitOverride::flagFor(CircuitKey::of('http.payments'))->value);
        self::assertSame('circuit-open.', FlagCircuitOverride::FORCE_OPEN_FLAG_PREFIX);
    }

    /**
     * The longest key still has a valid flag: the prefix fits within the length of a flag name.
     */
    public function testTheLongestKeyStillHasAFlag(): void
    {
        $flag = FlagCircuitOverride::flagFor(CircuitKey::of(str_repeat('k', CircuitKey::MAX_LENGTH)));

        self::assertSame(FlagName::MAX_LENGTH, \strlen($flag->value));
    }

    /**
     * @param array<string, bool> $states
     */
    private function override(array $states): FlagCircuitOverride
    {
        return new FlagCircuitOverride(new FlagEvaluator(
            [ConfiguredFlagProvider::fromConfiguration([], ['http.payments', 'slack-webhook'])],
            new AnonymousContextResolver(),
            new InMemoryFlagStore($states),
        ));
    }
}
