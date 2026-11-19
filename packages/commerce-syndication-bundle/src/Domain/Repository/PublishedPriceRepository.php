<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Repository;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Domain\Model\PublishedPrice;

/**
 * The prices a supplier published to its resellers. `Infrastructure\Doctrine\DoctrineSyndicationRepository` is the
 * Doctrine adapter.
 */
interface PublishedPriceRepository
{
    public function recordPrice(PublishedPrice $price): void;

    public function latestPrice(string $reseller, string $sku, string $currency): ?PublishedPrice;

    /**
     * The last price of one SKU published to each of these resellers, in one read.
     *
     * @param list<string> $resellers
     *
     * @return array<string, PublishedPrice> keyed by reseller; a reseller never sent the SKU in that currency is absent
     */
    public function latestPrices(string $sku, string $currency, array $resellers): array;

    /**
     * The prices of these SKUs in effect at some moment since then, in one read: for each SKU, the last one published
     * before, then every one published since, oldest first.
     *
     * @param list<string> $skus
     *
     * @return array<string, non-empty-list<PublishedPrice>> keyed by SKU; a SKU without a price in effect is absent
     */
    public function pricesInEffectSince(string $reseller, array $skus, string $currency, DateTimeImmutable $since): array;
}
