<?php

declare(strict_types=1);

namespace Phil\BulkActions\Run;

/**
 * What became of a run, item by item: what a result page shows, and what a retry starts from.
 */
final readonly class Report
{
    /**
     * @param list<ItemResult> $results in the order of the selection
     */
    public function __construct(
        public RunId $run,
        public RunStatus $status,
        public array $results,
        public bool $truncated,
    ) {
    }

    public function total(): int
    {
        return \count($this->results);
    }

    public function count(ItemOutcome $outcome): int
    {
        return \count($this->with($outcome));
    }

    /**
     * @return list<ItemResult>
     */
    public function with(ItemOutcome $outcome): array
    {
        return array_values(array_filter($this->results, static fn (ItemResult $result): bool => $outcome === $result->outcome));
    }

    /**
     * How many items a retry would handle again.
     */
    public function retryable(bool $conflicts = false): int
    {
        return \count(array_filter($this->results, static fn (ItemResult $result): bool => $result->outcome->isRetryable($conflicts)));
    }

    /**
     * The number of items of each outcome, in the order of the enumeration, zeros included.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (ItemOutcome::cases() as $outcome) {
            $counts[$outcome->value] = 0;
        }

        foreach ($this->results as $result) {
            ++$counts[$result->outcome->value];
        }

        return $counts;
    }
}
