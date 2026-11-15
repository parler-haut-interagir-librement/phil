<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

use Override;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * What a module would sell, in memory: three products, two taxed as goods and one as digital.
 */
final readonly class InMemorySellables implements SellableResolver
{
    #[Override]
    public function supports(string $type): bool
    {
        return 'product' === $type;
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        return match ($reference->id) {
            'dice' => Sellable::of($reference, 'Dice set', Money::of(1_999, Currency::of('CAD')), 'goods'),
            'pdf' => Sellable::of($reference, 'Rulebook PDF', Money::of(1_000, Currency::of('CAD')), 'digital'),
            'loot' => Sellable::of($reference, 'Mystery box', Money::of(3_000, Currency::of('CAD')), 'goods'),
            default => null,
        };
    }
}
