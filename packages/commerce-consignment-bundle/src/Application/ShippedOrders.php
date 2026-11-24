<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceConsignmentBundle\Application\Contract\IssueOrderPolicy;
use Phil\CommerceConsignmentBundle\Application\Contract\ShippedUnits;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Sale;
use Phil\Money\Money;

/**
 * Writes to the ledger what an order of `phil/commerce-bundle` shipped, at the unit price of its lines.
 *
 * The stock module ships the units; this service reads, after it, what left each location and sells that many units
 * in the ledger, under the order's number. A second call writes only what shipped since the first: run it after each
 * shipment, a partial one too, or again after a failed flush, and nothing is written twice.
 */
final readonly class ShippedOrders
{
    public function __construct(
        private Ledger $ledger,
        private ShippedUnits $shipped,
        private IssueOrderPolicy $issueOrders,
    ) {
    }

    /**
     * @return list<Sale> one per SKU, location and unit price written now; none when everything was written already
     */
    public function record(Order $order, DateTimeImmutable $at): array
    {
        $prices = $this->prices($order);
        $sales = [];

        foreach ($this->shipped->shipped($order->number) as $shipped) {
            $queue = $prices[$shipped->sku] ?? [];
            $ordered = array_sum(array_map(static fn (array $line): int => $line[1], $queue));
            $recorded = $this->ledger->sold($order->number, $shipped->sku);
            $missing = $shipped->quantity - $this->ledger->sold($order->number, $shipped->sku, $shipped->location);

            if ($missing <= 0) {
                continue;
            }

            if ($recorded + $missing > $ordered) {
                throw ConsignmentRefused::notOrdered($order->number, $shipped->sku, $recorded + $missing, $ordered);
            }

            foreach ($this->priced($queue, $recorded, $missing) as [$unitPrice, $quantity]) {
                $sales[] = $this->ledger->sell($order->number, $shipped->sku, $shipped->location, $quantity, $unitPrice, $this->issueOrders->issueOrder($shipped->sku), $at);
            }
        }

        return $sales;
    }

    /**
     * The unit prices of each SKU's product lines, in the order of the lines: two configured lines of a product share
     * its SKU and may differ in price.
     *
     * @return array<string, list<array{Money, int}>>
     */
    private function prices(Order $order): array
    {
        $prices = [];

        foreach ($order->lines() as $line) {
            if ('product' === $line->reference->type) {
                $prices[$line->reference->id][] = [$line->unitPrice, $line->quantity];
            }
        }

        return $prices;
    }

    /**
     * The units to write at each price: the lines' units after those written already, in the order of the lines.
     *
     * @param list<array{Money, int}> $queue
     *
     * @return list<array{Money, int}>
     */
    private function priced(array $queue, int $skip, int $wanted): array
    {
        $parts = [];

        foreach ($queue as [$unitPrice, $quantity]) {
            $skipped = min($skip, $quantity);
            $skip -= $skipped;
            $part = min($wanted, $quantity - $skipped);

            if ($part > 0) {
                $parts[] = [$unitPrice, $part];
                $wanted -= $part;
            }
        }

        return $parts;
    }
}
