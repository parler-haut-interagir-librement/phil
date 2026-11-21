<?php

declare(strict_types=1);

namespace Phil\ImportWizard\Tests\Support;

use Phil\ImportWizard\Contract\ImportTarget;
use Phil\ImportWizard\Import\Batch;
use Phil\ImportWizard\Plan\CellError;
use Phil\ImportWizard\Plan\NaturalKey;
use Phil\ImportWizard\Plan\PlannedRow;

/**
 * A target that keeps its records in memory, refuses the keys it is told to, and remembers every lookup and batch.
 */
final class MemoryTarget implements ImportTarget
{
    /** @var list<array{string, list<string>}> */
    public array $lookups = [];

    /** @var list<Batch> */
    public array $batches = [];

    /**
     * @param array<string, list<string>>              $existing the key texts of the records that exist, by type
     * @param array<string, non-empty-list<CellError>> $refuse   the errors of the key texts to refuse
     */
    public function __construct(
        private readonly string $type,
        public array $existing = [],
        private readonly array $refuse = [],
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function existing(string $type, array $keys): array
    {
        $this->lookups[] = [$type, array_map(static fn (NaturalKey $key): string => $key->toString(), $keys)];

        return array_values(array_filter($keys, fn (NaturalKey $key): bool => \in_array($key->toString(), $this->existing[$type] ?? [], true)));
    }

    public function write(Batch $batch): array
    {
        $this->batches[] = $batch;
        $errors = [];

        foreach ($batch->rows as $row) {
            $refused = $this->refuse[$row->key->toString()] ?? null;

            if (null === $refused) {
                $this->existing[$this->type][] = $row->key->toString();
            } else {
                $errors[$row->line] = $refused;
            }
        }

        return $errors;
    }

    /**
     * @return list<list<int>> the lines of each batch written
     */
    public function batchLines(): array
    {
        return array_map(static fn (Batch $batch): array => array_map(static fn (PlannedRow $row): int => $row->line, $batch->rows), $this->batches);
    }
}
