<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\SettingsBundle\Domain\Migration\StoredValue;

/**
 * What the migrations read and write of the stored values: every value of a key, whoever it is of, and the versions
 * already applied, recorded in the same transaction as their changes.
 */
interface SettingMigrationStore
{
    /**
     * @return list<string> the versions applied
     */
    public function appliedMigrations(): array;

    /**
     * @return list<StoredValue> every stored value of the key
     */
    public function valuesOf(string $key): array;

    /**
     * Writes the values given, removes those whose value is null, and records the version, at once.
     *
     * @param list<StoredValue> $changes
     */
    public function applyMigration(string $version, array $changes, DateTimeImmutable $at): void;
}
