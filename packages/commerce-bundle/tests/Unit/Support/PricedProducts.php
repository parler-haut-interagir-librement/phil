<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Products of type `product` at fixed prices in cents of CAD, taxed as goods.
 */
final readonly class PricedProducts implements SellableResolver
{
    /**
     * @param array<string, int> $prices id → price in cents
     */
    public function __construct(
        private array $prices = ['dice' => 1_000, 'mat' => 3_000, 'pen' => 250],
    ) {
    }

    #[Override]
    public function supports(string $type): bool
    {
        return 'product' === $type;
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        $price = $this->prices[$reference->id] ?? null;

        return null === $price ? null : Sellable::of($reference, ucfirst($reference->id), Money::of($price, Currency::of('CAD')), 'goods');
    }
}
