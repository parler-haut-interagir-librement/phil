<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Phil\PrivacyBundle\Application\Contract\AnonymizationTarget;
use Phil\PrivacyBundle\Application\Exception\AnonymizationRefused;
use Throwable;

/**
 * Tables in memory, keyed by their single-column `id` key, with a transaction that restores them when the work throws.
 */
final class MemoryTarget implements AnonymizationTarget
{
    /** @var list<string> */
    public array $log = [];

    /**
     * @param array<string, list<array<string, mixed>>>       $tables
     * @param array<string, non-empty-list<non-empty-string>> $keys
     */
    public function __construct(
        public array $tables,
        private readonly array $keys = [],
    ) {
    }

    public function keyOf(string $table): array
    {
        $this->log[] = 'key '.$table;

        return $this->keys[$table] ?? (isset($this->tables[$table]) ? ['id'] : throw AnonymizationRefused::withoutKey($table));
    }

    public function rows(string $table, array $key, array $columns): iterable
    {
        $this->log[] = 'rows '.$table.' '.implode(',', $key).' '.implode(',', $columns);

        foreach ($this->tables[$table] as $row) {
            yield array_intersect_key($row, array_flip([...$key, ...$columns]));
        }
    }

    public function update(string $table, array $key, array $values): void
    {
        $this->log[] = 'update '.$table.' '.json_encode($key, \JSON_THROW_ON_ERROR);

        foreach ($this->tables[$table] as $index => $row) {
            if (array_intersect_key($row, $key) === $key) {
                $this->tables[$table][$index] = [...$row, ...$values];
            }
        }
    }

    public function transactional(callable $work): mixed
    {
        $before = $this->tables;
        $this->log[] = 'begin';

        try {
            $result = $work();
            $this->log[] = 'commit';

            return $result;
        } catch (Throwable $throwable) {
            $this->tables = $before;
            $this->log[] = 'rollback';

            throw $throwable;
        }
    }
}
