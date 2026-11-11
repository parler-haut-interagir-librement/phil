<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Infrastructure\Configuration;

use Override;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Application\Circuit\FlagCircuitOverride;
use Phil\FeatureFlagsBundle\Domain\Flag\EnumerableFlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagDefinition;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagKind;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagMetadata;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;

/**
 * The flags declared in the application configuration, in the shape of {@see FlagDefinitionFormat}:
 *
 * ```yaml
 * flags:
 *     new-checkout:
 *         active: true
 *         rules:
 *             - { attribute: tenant, members: [tenant-a, tenant-b] }
 *             - { attribute: tenant, percentage: 20 }
 * ```
 *
 * Each circuit declared to `phil/circuit-breaker-bundle` gets its force-open flag, `circuit-open.<key>`, inactive,
 * without rules and of kind `ops`, unless the configuration declares it itself.
 */
final readonly class ConfiguredFlagProvider implements EnumerableFlagProvider
{
    /**
     * @param array<string, FlagDefinition> $definitions
     */
    private function __construct(
        private array $definitions,
    ) {
    }

    /**
     * @param array<array-key, mixed> $flags       flag name => definition, in the shape of {@see FlagDefinitionFormat}
     * @param list<int|string>        $circuitKeys the keys of the declared circuits
     *
     * @throws InvalidValue
     */
    public static function fromConfiguration(array $flags, array $circuitKeys = []): self
    {
        $definitions = [];

        foreach ($flags as $name => $flag) {
            try {
                $definition = FlagDefinitionFormat::read(FlagName::of((string) $name), $flag);
            } catch (InvalidValue $invalidValue) {
                throw InvalidValue::because(\sprintf('flags.%s: %s', $name, $invalidValue->getMessage()));
            }

            $definitions[$definition->name->value] = $definition;
        }

        foreach ($circuitKeys as $key) {
            try {
                $flag = FlagCircuitOverride::flagFor(CircuitKey::of((string) $key));
            } catch (InvalidCircuit $invalidCircuit) {
                throw InvalidValue::because(\sprintf('circuits.%s: %s', $key, $invalidCircuit->getMessage()));
            }

            $definitions[$flag->value] ??= new FlagDefinition($flag, false, metadata: FlagMetadata::of(
                \sprintf('Forces the circuit "%s" open.', $key),
                kind: FlagKind::Ops,
            ));
        }

        return new self($definitions);
    }

    #[Override]
    public function find(FlagName $name): ?FlagDefinition
    {
        return $this->definitions[$name->value] ?? null;
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->definitions);
    }
}
