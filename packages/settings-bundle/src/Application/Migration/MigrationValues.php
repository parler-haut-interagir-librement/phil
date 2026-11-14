<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Application\Migration;

use Phil\SettingsBundle\Domain\Contract\SettingMigrationStore;
use Phil\SettingsBundle\Domain\Migration\StoredValue;

/**
 * The values of the keys one migration touches, read once from the store, changed step by step, and compared at the
 * end with what was read.
 *
 * @internal
 */
final class MigrationValues
{
    /** @var array<string, array<string, StoredValue>> as read, by key then subject */
    private array $read = [];

    /** @var array<string, array<string, StoredValue>> as the steps left them, by key then subject */
    private array $current = [];

    public function __construct(
        private readonly SettingMigrationStore $store,
    ) {
    }

    /**
     * @return array<string, StoredValue> by subject, `user:42` or `site:site`
     */
    public function of(string $key): array
    {
        if (!isset($this->read[$key])) {
            $values = [];

            foreach ($this->store->valuesOf($key) as $stored) {
                $values[$stored->scope.':'.$stored->subject] = $stored;
            }

            $this->read[$key] = $values;
            $this->current[$key] = $values;
        }

        return $this->current[$key];
    }

    /**
     * @param array<string, StoredValue> $values by subject
     */
    public function replace(string $key, array $values): void
    {
        $this->of($key);
        $this->current[$key] = $values;
    }

    /**
     * @return list<StoredValue> the values written, and those removed with a null value
     */
    public function changes(): array
    {
        $changes = [];

        foreach ($this->read as $key => $read) {
            foreach ($read as $subject => $stored) {
                if (!isset($this->current[$key][$subject])) {
                    $changes[] = new StoredValue($stored->scope, $stored->subject, $key, null);
                }
            }

            foreach ($this->current[$key] as $subject => $stored) {
                if ($stored->value !== ($read[$subject] ?? null)?->value) {
                    $changes[] = $stored;
                }
            }
        }

        return $changes;
    }
}
