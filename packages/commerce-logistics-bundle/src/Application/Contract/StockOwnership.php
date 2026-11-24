<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Application\Contract;

/**
 * Says whose units leave a stock level when the shop's own units and units left on consignment share it: a shipment,
 * a write-off, a transfer out. `Application\ShopUnitsFirst` is the module's answer; an application, or a consignment
 * module, points the alias of this interface at its own.
 */
interface StockOwnership
{
    /**
     * @param int                      $units     the units leaving, one or more
     * @param int                      $shopUnits the shop's own units at the level before they leave
     * @param list<array{string, int}> $consigned each owner and their units at the level before they leave, the owner
     *                                            whose units came first first
     * @param string|null              $reference what they leave for: an order's number, a count sheet, a transfer
     *
     * @return list<array{string, int}> each owner whose units are among those leaving, and how many, each no more than
     *                                  they hold; the rest are the shop's, no more than it holds
     */
    public function leaving(string $sku, string $location, int $units, int $shopUnits, array $consigned, ?string $reference): array;
}
