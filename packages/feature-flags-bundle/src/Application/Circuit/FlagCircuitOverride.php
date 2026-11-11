<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Circuit;

use Override;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Contract\CircuitOverride;
use Phil\FeatureFlagsBundle\Application\Flag\FlagEvaluator;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/**
 * A circuit is forced open while its flag, `circuit-open.<key>`, is on. Forcing a circuit open is therefore a
 * toggle like any other: authorized, with a reason, audited, and read once per request like any flag.
 *
 * A flag no provider declares is off, so a circuit whose flag is missing is never forced open. Every circuit key has
 * a valid flag: a key is at most `CircuitKey::MAX_LENGTH` bytes, the flag name `FlagName::MAX_LENGTH`, and the prefix
 * fills the difference.
 */
final readonly class FlagCircuitOverride implements CircuitOverride
{
    public const string FORCE_OPEN_FLAG_PREFIX = 'circuit-open.';

    public function __construct(
        private FlagEvaluator $flags,
    ) {
    }

    /**
     * The flag whose activation forces the circuit of this key open.
     */
    public static function flagFor(CircuitKey $key): FlagName
    {
        return FlagName::of(self::FORCE_OPEN_FLAG_PREFIX.$key->value);
    }

    #[Override]
    public function isForcedOpen(CircuitKey $key): bool
    {
        return $this->flags->isEnabled(self::flagFor($key));
    }
}
