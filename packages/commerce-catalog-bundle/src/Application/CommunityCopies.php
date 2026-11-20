<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Application;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\Event\OrderPurchased;
use Phil\CommerceBundle\Domain\Model\Order;
use Phil\CommerceBundle\Domain\Repository\OrderRepository;
use Phil\CommerceBundle\Domain\ValueObject\OrderLine;
use Phil\CommerceCatalogBundle\Application\Contract\CommunityCopyLedger;
use Phil\CommerceCatalogBundle\Domain\Exception\CatalogRefused;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyClaim;
use Phil\CommerceCatalogBundle\Domain\Model\CommunityCopyFunding;
use Phil\CommerceCatalogBundle\Domain\Model\Product;
use Phil\CommerceCatalogBundle\Domain\Repository\ProductRepository;
use Phil\Money\Money;

/**
 * Community copies: copies of a product that other buyers fund, at its copy price, and that people who cannot pay
 * claim, one each.
 *
 * A paid order funds them two ways, before its promotions and taxes: each `community_copy:<sku>` line gives what it
 * costs, and each `pay_what_you_want:<sku>` line gives what its buyer paid above the suggested price — above the base
 * price when none is suggested —, the price chosen being its `price` option. A copy is there to claim for each copy price funded, less the copies
 * claimed. An order funds once, however many times it is counted.
 *
 * `fund()` answers the core's `OrderPurchased`; `claim()` runs inside a transaction, which locks the product so that
 * its last copy goes to one claimant only.
 */
final readonly class CommunityCopies
{
    public function __construct(
        private ProductRepository $products,
        private CommunityCopyLedger $ledger,
        private ?OrderRepository $orders = null,
    ) {
    }

    /**
     * What the product's copies were funded with, in its currency; `null` when it offers none.
     */
    public function funded(Product $product): ?Money
    {
        return $product->communityCopyPrice() instanceof Money ? Money::of($this->ledger->funded($product->sku), $product->price->currency) : null;
    }

    /**
     * How many copies are left to claim: one per copy price funded, less those claimed.
     */
    public function available(Product $product): int
    {
        $copyPrice = $product->communityCopyPrice();

        if (!$copyPrice instanceof Money) {
            return 0;
        }

        return max(0, intdiv($this->ledger->funded($product->sku), $copyPrice->minorUnits) - $this->ledger->claimed($product->sku));
    }

    /**
     * Gives a copy to someone who cannot pay, named by the application's stable identifier: one per person and
     * product. The caller delivers it — a download, a copy set aside.
     */
    public function claim(Product $product, string $claimant, DateTimeImmutable $at): CommunityCopyClaim
    {
        if (!$product->communityCopyPrice() instanceof Money) {
            throw CatalogRefused::noCommunityCopies($product->sku);
        }

        $claim = CommunityCopyClaim::of($product->sku, $claimant, $at);
        $this->ledger->lock($product->sku);

        if ($this->ledger->hasClaimed($product->sku, $claimant)) {
            throw CatalogRefused::alreadyClaimed($product->sku);
        }

        if ($this->available($product) < 1) {
            throw CatalogRefused::noCommunityCopyLeft($product->sku);
        }

        $this->ledger->addClaim($claim);

        return $claim;
    }

    /**
     * Records what a paid order gave towards community copies; returns the products it funded.
     *
     * @return list<string> the SKUs, in the order of the order's lines
     */
    public function fund(Order $order, DateTimeImmutable $at): array
    {
        // By position rather than keyed by SKU: a SKU of digits would become an integer key.
        $given = [];
        $positions = [];

        foreach ($order->lines() as $line) {
            $sku = PriceRuleSellables::skuOf($line->reference);
            $product = null === $sku ? null : $this->products->find($sku);

            if (!$product instanceof Product || !$product->communityCopyPrice() instanceof Money || !$product->price->currency->equals($order->currency)) {
                continue;
            }

            $position = $positions[$product->sku] ??= \count($given);
            $given[$position] = [$product->sku, ($given[$position][1] ?? 0) + $this->given($product, $line) * $line->quantity];
        }

        $funded = [];

        foreach ($given as [$sku, $amount]) {
            if ($amount > 0 && !$this->ledger->isFundedBy($sku, $order->number)) {
                $this->ledger->addFunding(CommunityCopyFunding::of($sku, $order->number, $amount, $at));
                $funded[] = $sku;
            }
        }

        return $funded;
    }

    /**
     * Funds from the order the core says was paid in full.
     *
     * @return list<string> the SKUs funded
     */
    public function __invoke(OrderPurchased $event): array
    {
        $order = $this->orders?->find($event->orderNumber);

        return $order instanceof Order ? $this->fund($order, $event->occurredAt()) : [];
    }

    /**
     * What one unit of the line gives: a community copy all of its price; a chosen price what lies above the
     * suggested price, or the base price.
     */
    private function given(Product $product, OrderLine $line): int
    {
        if (PriceRuleSellables::COMMUNITY_COPY === $line->reference->type) {
            return $line->unitPrice->minorUnits;
        }

        $floor = ($product->suggestedPrice() ?? $product->price)->minorUnits;

        // A line without its price option gives nothing: it is taken to be at the floor.
        return max(0, (PriceRuleSellables::chosenPriceOf($line) ?? $floor) - $floor);
    }
}
