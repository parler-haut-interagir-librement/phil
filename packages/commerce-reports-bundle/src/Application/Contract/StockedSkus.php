<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Contract;

use Phil\CommerceReportsBundle\Domain\ValueObject\StockedSku;

/**
 * The SKUs the shop keeps stock of, with their names and categories. Neither the catalogue nor the logistics module
 * lists them: an application aliases this port to its own service; without one, the stock reports know only the SKUs
 * sold in their period.
 */
interface StockedSkus
{
    /**
     * Every SKU, yielded as it is read, each once.
     *
     * @return iterable<StockedSku>
     */
    public function all(): iterable;

    /**
     * The SKU, or null when it is not stocked.
     */
    public function find(string $sku): ?StockedSku;
}
