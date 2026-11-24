<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Infrastructure\Memory;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockedSku;

/**
 * Stocked SKUs given once, for tests and until an application lists its own: a SKU given twice keeps its last name
 * and category. Without SKUs, the stock reports know only the SKUs sold in their period.
 */
final readonly class InMemoryStockedSkus implements StockedSkus
{
    /** @var array<string, StockedSku> */
    private array $skus;

    /**
     * @param list<StockedSku> $skus
     */
    public function __construct(array $skus = [])
    {
        $bySku = [];

        foreach ($skus as $sku) {
            $bySku[$sku->sku] = $sku;
        }

        $this->skus = $bySku;
    }

    #[Override]
    public function all(): iterable
    {
        yield from $this->skus;
    }

    #[Override]
    public function find(string $sku): ?StockedSku
    {
        return $this->skus[$sku] ?? null;
    }
}
