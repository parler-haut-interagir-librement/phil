<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application;

use Override;
use Phil\CommerceLogisticsBundle\Application\Contract\StockOwnership;

/**
 * The shop's own units leave first; once none are left, the owners' units leave, the owner whose units came first
 * first.
 */
final readonly class ShopUnitsFirst implements StockOwnership
{
    #[Override]
    public function leaving(string $sku, string $location, int $units, int $shopUnits, array $consigned, ?string $reference): array
    {
        $left = $units - min($units, $shopUnits);
        $leaving = [];

        foreach ($consigned as [$owner, $held]) {
            $part = min($left, $held);

            if ($part > 0) {
                $leaving[] = [$owner, $part];
                $left -= $part;
            }
        }

        return $leaving;
    }
}
