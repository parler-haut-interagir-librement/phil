<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Domain\Shop;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The shop an order was placed in, and the catalogues its lines come from: what an order is tagged with, so that a
 * central back office can manage the orders of every shop and still tell where each one came from.
 *
 * Every catalogue is one the shop sells, and the shop's own catalogue need not have a line.
 */
final readonly class OrderOrigin
{
    /**
     * @param list<TenantId> $catalogues
     */
    private function __construct(
        public TenantId $shop,
        public array $catalogues,
    ) {
    }

    /**
     * @param list<TenantId> $catalogues the catalogues of the order's lines; duplicates count once
     *
     * @throws InvalidValue when a catalogue is not one the shop sells
     */
    public static function placedIn(TenantId $shop, ShopNetwork $network, array $catalogues = []): self
    {
        $distinct = [];

        foreach ($catalogues as $catalogue) {
            if (!$network->sells($shop, $catalogue)) {
                throw InvalidValue::because(\sprintf('The shop "%s" does not sell the catalogue of "%s".', $shop->value, $catalogue->value));
            }

            $distinct[$catalogue->value] = $catalogue;
        }

        return new self($shop, array_values($distinct));
    }

    /**
     * Whether the order holds lines of another shop's catalogue, paid at this shop's checkout.
     */
    public function spansCatalogues(): bool
    {
        return array_any($this->catalogues, fn (TenantId $catalogue): bool => !$catalogue->equals($this->shop));
    }
}
