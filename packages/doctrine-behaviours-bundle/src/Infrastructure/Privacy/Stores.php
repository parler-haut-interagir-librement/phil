<?php

declare(strict_types=1);

namespace Phil\DoctrineBehavioursBundle\Infrastructure\Privacy;

use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;

/**
 * The stores of an erasure report: every table of the behaviour, found or not, so that a second run reports the same;
 * the module itself when no table has the behaviour yet, since a report names at least one store.
 *
 * @internal
 */
final class Stores
{
    /**
     * @param list<string> $tables
     *
     * @return non-empty-array<string, ErasureEffect>
     */
    public static function of(array $tables, string $module, ErasureEffect $effect): array
    {
        $stores = [];

        foreach ([] === $tables ? [$module] : $tables as $store) {
            $stores[$store] = $effect;
        }

        return $stores;
    }
}
