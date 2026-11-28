<?php

declare(strict_types=1);

namespace Phil\RelatedContent\Pairs;

use Phil\RelatedContent\Contract\PairCounts;
use Phil\RelatedContent\Exception\InvalidRule;
use Phil\RelatedContent\Item;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Pair counts in memory, for tests and for a process that rebuilds them from its orders at start. An application keeps
 * them in its database through its own `PairCounts`.
 */
final class InMemoryPairCounts implements PairCounts, ResetInterface
{
    /**
     * @var array<string, array<string, array<string, PairCount>>> by signal, from, to: the count seen from the first
     */
    private array $counts = [];

    public function add(Signal $signal, string $from, string $to, int $count = 1): void
    {
        Item::assertIdentifier($from);
        Item::assertIdentifier($to);

        if ($count < 1) {
            throw InvalidRule::outOfRange('count', $count, 1, \PHP_INT_MAX);
        }

        if ($from === $to) {
            return;
        }

        $this->increment($signal, $from, $to, $count);

        if ($signal->isSymmetric()) {
            $this->increment($signal, $to, $from, $count);
        }
    }

    public function top(Signal $signal, string $id, int $limit): array
    {
        $row = $this->counts[$signal->value][$id] ?? [];
        usort($row, static fn (PairCount $a, PairCount $b): int => [$b->count, $a->other] <=> [$a->count, $b->other]);

        return \array_slice($row, 0, max(0, $limit));
    }

    public function forget(string $id): void
    {
        foreach ($this->counts as $signal => $rows) {
            unset($rows[$id]);

            foreach ($rows as $from => $row) {
                unset($row[$id]);
                $rows[$from] = $row;
            }

            $this->counts[$signal] = $rows;
        }
    }

    private function increment(Signal $signal, string $from, string $to, int $count): void
    {
        $known = $this->counts[$signal->value][$from][$to] ?? null;
        $this->counts[$signal->value][$from][$to] = new PairCount($to, ($known->count ?? 0) + $count);
    }

    public function reset(): void
    {
        $this->counts = [];
    }
}
