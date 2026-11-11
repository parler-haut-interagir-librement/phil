<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Application\Flag;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\DiagnosableFlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\EnumerableFlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagProvider;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagStateSource;

/**
 * The current configuration of the flags, for a listing, an admin screen or a test that fails on an expired
 * flag: each flag with its declaring provider and its stored state, on the same precedence as the evaluator —
 * the first provider that knows a flag declares it, and a later one that knows it too is shadowed.
 *
 * **A flag its provider holds but cannot read is unreadable, not absent.** It takes its place in the precedence
 * as the evaluator gives it (which answers `PARSE_ERROR` for it): {@see self::unreadable()} lists it with its
 * reason, {@see self::lookup()} returns it as an {@see UnreadableEntry}, and {@see self::find()} throws
 * `InvalidValue` for it instead of answering `null`.
 *
 * It reads no context and evaluates nothing.
 */
final readonly class FlagCatalog
{
    /**
     * @param array<string, FlagProvider> $providers the evaluator's providers, in order, by the name a listing shows
     */
    public function __construct(
        private array $providers,
        private ?FlagStateSource $states = null,
    ) {
    }

    /**
     * Every flag the enumerable providers know and can read, by name. A provider that cannot list its flags
     * contributes none.
     *
     * @return list<CatalogEntry>
     */
    public function entries(): array
    {
        return array_values(array_filter($this->listing(), static fn (CatalogEntry|UnreadableEntry $entry): bool => $entry instanceof CatalogEntry));
    }

    /**
     * Every flag a provider that can say so holds but cannot read, by name, unless an earlier provider declares it.
     *
     * @return list<UnreadableEntry>
     */
    public function unreadable(): array
    {
        return array_values(array_filter($this->listing(), static fn (CatalogEntry|UnreadableEntry $entry): bool => $entry instanceof UnreadableEntry));
    }

    /**
     * The flag as its declaring provider holds it, or `null` when no provider knows it.
     *
     * @throws InvalidValue when its declaring provider cannot read it
     */
    public function find(FlagName $flag): ?CatalogEntry
    {
        $entry = $this->lookup($flag);

        if ($entry instanceof UnreadableEntry) {
            throw InvalidValue::because($entry->reason);
        }

        return $entry;
    }

    /**
     * The flag as its declaring provider holds it, unreadable included, or `null` when no provider knows it.
     */
    public function lookup(FlagName $flag): CatalogEntry|UnreadableEntry|null
    {
        foreach ($this->providers as $source => $provider) {
            try {
                $definition = $provider->find($flag);
            } catch (InvalidValue $invalidValue) {
                return new UnreadableEntry($flag->value, $invalidValue->getMessage(), $source);
            }

            if (null !== $definition) {
                return new CatalogEntry($definition, $source, $this->states?->isActive($flag));
            }
        }

        return null;
    }

    /**
     * @return array<string, CatalogEntry|UnreadableEntry> by name
     */
    private function listing(): array
    {
        $entries = [];

        foreach ($this->providers as $source => $provider) {
            if (!$provider instanceof EnumerableFlagProvider) {
                continue;
            }

            foreach ($provider->all() as $definition) {
                $entries[$definition->name->value] ??= new CatalogEntry($definition, $source, $this->states?->isActive($definition->name));
            }

            if ($provider instanceof DiagnosableFlagProvider) {
                foreach ($provider->unreadable() as $flag) {
                    $entries[$flag->name] ??= new UnreadableEntry($flag->name, $flag->reason, $source);
                }
            }
        }

        ksort($entries, \SORT_STRING);

        return $entries;
    }
}
