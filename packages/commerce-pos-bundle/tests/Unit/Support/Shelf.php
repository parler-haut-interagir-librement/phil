<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * Products at fixed prices in Canadian dollars, for the tests: `TEA` at 4.99, `MUG` at 12.00, `PIN` at
 * 0.01, `GOLD`, priced so that a large quantity overflows an amount, `LOOT`, a loot box at 10.00 whose dice are
 * drawn by `loot-dice`, and `CHEST`, a larger one at 20.00 drawn the same way; and a ticket, `ticket` `GALA`, at 25.00.
 */
final class Shelf implements SellableResolver, SellableOptions
{
    private const array PRICES = ['TEA' => 499, 'MUG' => 1_200, 'PIN' => 1, 'GOLD' => \PHP_INT_MAX >> 10, 'LOOT' => 1_000, 'CHEST' => 2_000];

    #[Override]
    public function supports(string $type): bool
    {
        return 'product' === $type || 'ticket' === $type;
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        if ('ticket' === $reference->type) {
            return 'GALA' === $reference->id ? Sellable::of($reference, 'Gala night', Money::of(2_500, Currency::of('CAD')), 'standard') : null;
        }

        $price = self::PRICES[$reference->id] ?? null;

        return null === $price ? null : Sellable::of($reference, 'Product '.$reference->id, Money::of($price, Currency::of('CAD')), 'standard');
    }

    #[Override]
    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
    {
        return \in_array($reference->id, ['LOOT', 'CHEST'], true) ? OptionSet::of(OptionDefinition::draw('dice', 'Dice', 'loot-dice')) : OptionSet::none();
    }
}
