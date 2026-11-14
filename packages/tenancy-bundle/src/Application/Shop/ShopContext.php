<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Application\Shop;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\Exception\NoActiveTenant;
use Phil\TenancyBundle\Domain\Shop\OrderOrigin;
use Phil\TenancyBundle\Domain\Shop\ShopNetwork;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * The shop of the current request — the active tenant — read against the shop network: what a commerce bundle asks
 * to know which catalogues to show, whether a product may be put in the cart, and what to tag an order with.
 *
 * It holds nothing of its own: the shop is the tenant context's, which is left between requests.
 */
final readonly class ShopContext
{
    public function __construct(
        private TenantContext $context,
        private ShopNetwork $network,
    ) {
    }

    public function current(): ?TenantId
    {
        return $this->context->current();
    }

    /**
     * The catalogues the current shop sells, its own first; none outside a shop.
     *
     * @return list<TenantId>
     */
    public function catalogues(): array
    {
        $shop = $this->context->current();

        return $shop instanceof TenantId ? $this->network->catalogues($shop) : [];
    }

    /**
     * Whether a product of this catalogue may be put in the current shop's cart; never outside a shop.
     */
    public function sells(TenantId $catalogue): bool
    {
        $shop = $this->context->current();

        return $shop instanceof TenantId && $this->network->sells($shop, $catalogue);
    }

    /**
     * What an order placed now is tagged with.
     *
     * @param list<TenantId> $catalogues the catalogues of the order's lines
     *
     * @throws NoActiveTenant outside a shop
     */
    public function origin(array $catalogues = []): OrderOrigin
    {
        return OrderOrigin::placedIn($this->context->require(), $this->network, $catalogues);
    }
}
