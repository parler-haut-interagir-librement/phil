<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Contract;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\DeferredSale;

interface DeferredSaleRepository
{
    public function findDeferredSale(string $orderNumber): ?DeferredSale;

    public function addDeferredSale(DeferredSale $sale): void;

    /**
     * The orders whose deposit is paid and whose balance, called by a date, is due at `$at`: the earliest due first,
     * at most `$limit` of them.
     *
     * @param positive-int $limit
     *
     * @return list<string> the order numbers
     */
    public function dueForBalance(DateTimeImmutable $at, int $limit): array;
}
