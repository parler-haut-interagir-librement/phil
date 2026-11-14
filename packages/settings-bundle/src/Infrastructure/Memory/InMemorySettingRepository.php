<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Memory;

use DateTimeImmutable;
use Override;
use Phil\SettingsBundle\Domain\Contract\SettingMigrationStore;
use Phil\SettingsBundle\Domain\Contract\SettingRepository;
use Phil\SettingsBundle\Domain\Migration\StoredValue;
use Phil\SettingsBundle\Domain\ValueObject\Subject;

/**
 * The values in memory, for tests: they last as long as the service.
 */
final class InMemorySettingRepository implements SettingRepository, SettingMigrationStore
{
    /** @var array<string, array<string, string>> by subject, then by key */
    private array $values = [];

    /** @var list<string> */
    private array $migrations = [];

    #[Override]
    public function values(Subject $subject): array
    {
        return $this->values[$subject->key()] ?? [];
    }

    #[Override]
    public function save(Subject $subject, array $values, array $removed, DateTimeImmutable $at): void
    {
        $stored = array_diff_key($this->values[$subject->key()] ?? [], array_flip($removed));
        $this->values[$subject->key()] = array_merge($stored, $values);
    }

    #[Override]
    public function erase(Subject $subject): array
    {
        $keys = array_keys($this->values[$subject->key()] ?? []);
        sort($keys, \SORT_STRING);
        unset($this->values[$subject->key()]);

        return $keys;
    }

    #[Override]
    public function appliedMigrations(): array
    {
        return $this->migrations;
    }

    #[Override]
    public function valuesOf(string $key): array
    {
        $values = [];

        foreach ($this->values as $subject => $stored) {
            if (isset($stored[$key])) {
                [$scope, $id] = explode(':', $subject, 2);
                $values[] = new StoredValue($scope, $id, $key, $stored[$key]);
            }
        }

        return $values;
    }

    #[Override]
    public function applyMigration(string $version, array $changes, DateTimeImmutable $at): void
    {
        foreach ($changes as $change) {
            $subject = $change->scope.':'.$change->subject;
            unset($this->values[$subject][$change->key]);

            if (null !== $change->value) {
                $this->values[$subject][$change->key] = $change->value;
            }
        }

        $this->migrations[] = $version;
    }
}
