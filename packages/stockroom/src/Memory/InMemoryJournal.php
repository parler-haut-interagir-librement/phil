<?php

declare(strict_types=1);

namespace Phil\Stockroom\Memory;

use Override;
use Phil\Stockroom\Contract\Journal;
use Phil\Stockroom\Movement;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The journal in memory, for tests and short scripts; emptied by `reset()` between the requests of a worker.
 */
final class InMemoryJournal implements Journal, ResetInterface
{
    /** @var list<Movement> in the order they were written */
    private array $movements = [];

    #[Override]
    public function append(Movement $movement): void
    {
        $this->movements[] = $movement;
    }

    #[Override]
    public function ofItem(string $item, int $limit): array
    {
        return $this->latest(static fn (Movement $movement): bool => $item === $movement->item, $limit);
    }

    #[Override]
    public function ofReference(string $reference, int $limit): array
    {
        return $this->latest(static fn (Movement $movement): bool => $reference === $movement->reference, $limit);
    }

    #[Override]
    public function reset(): void
    {
        $this->movements = [];
    }

    /**
     * @param callable(Movement): bool $filter
     *
     * @return list<Movement>
     */
    private function latest(callable $filter, int $limit): array
    {
        return \array_slice(array_filter(array_reverse($this->movements), $filter), 0, max(0, $limit));
    }
}
