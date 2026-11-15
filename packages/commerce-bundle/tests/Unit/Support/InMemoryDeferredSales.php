<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\Contract\DeferredSaleRepository;
use Phil\CommerceBundle\Domain\Deferred\BalanceTrigger;
use Phil\CommerceBundle\Domain\Model\DeferredSale;

/**
 * Deferred sales, in memory; the balances due are those of `DeferredSale::waitsFor()`, by due date.
 */
final class InMemoryDeferredSales implements DeferredSaleRepository
{
    /** @var array<string, DeferredSale> */
    public array $sales = [];

    #[Override]
    public function findDeferredSale(string $orderNumber): ?DeferredSale
    {
        return $this->sales[$orderNumber] ?? null;
    }

    #[Override]
    public function addDeferredSale(DeferredSale $sale): void
    {
        $this->sales[$sale->orderNumber] = $sale;
    }

    #[Override]
    public function dueForBalance(DateTimeImmutable $at, int $limit): array
    {
        $due = array_filter($this->sales, static fn (DeferredSale $sale): bool => $sale->waitsFor(BalanceTrigger::Date) && $sale->dueAt instanceof DateTimeImmutable && $sale->dueAt <= $at);
        uasort($due, static fn (DeferredSale $a, DeferredSale $b): int => $a->dueAt <=> $b->dueAt);

        return \array_slice(array_map(static fn (DeferredSale $sale): string => $sale->orderNumber, array_values($due)), 0, $limit);
    }
}
