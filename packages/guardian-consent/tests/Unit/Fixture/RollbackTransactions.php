<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\GuardianConsent\Contract\Transactions;
use ReflectionObject;
use Throwable;

/**
 * A database's transactions over stores in memory: on a throwable, every store is put back as it was before the work.
 */
final class RollbackTransactions implements Transactions
{
    public int $runs = 0;

    public int $rollbacks = 0;

    /** @var list<object> */
    private readonly array $stores;

    public function __construct(object ...$stores)
    {
        $this->stores = array_values($stores);
    }

    #[Override]
    public function run(Closure $work): mixed
    {
        ++$this->runs;
        $snapshots = array_map($this->snapshot(...), $this->stores);

        try {
            return $work();
        } catch (Throwable $throwable) {
            ++$this->rollbacks;

            foreach ($this->stores as $index => $store) {
                $this->restore($store, $snapshots[$index]);
            }

            throw $throwable;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(object $store): array
    {
        $values = [];

        foreach (new ReflectionObject($store)->getProperties() as $property) {
            $values[$property->getName()] = $property->getValue($store);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function restore(object $store, array $values): void
    {
        $reflection = new ReflectionObject($store);

        foreach ($values as $name => $value) {
            $reflection->getProperty($name)->setValue($store, $value);
        }
    }
}
