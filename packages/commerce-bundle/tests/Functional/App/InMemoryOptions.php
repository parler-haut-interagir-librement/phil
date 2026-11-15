<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

use Override;
use Phil\CommerceBundle\Application\Port\SellableOptions;
use Phil\CommerceBundle\Domain\ValueObject\OptionDefinition;
use Phil\CommerceBundle\Domain\ValueObject\OptionSet;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceBundle\Domain\ValueObject\TextConstraints;
use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * What a catalogue would declare, in memory: an optional engraving on the dice and a draw in the mystery box.
 */
final readonly class InMemoryOptions implements SellableOptions
{
    #[Override]
    public function supports(string $type): bool
    {
        return 'product' === $type;
    }

    #[Override]
    public function optionsOf(SellableReference $reference, Currency $currency): OptionSet
    {
        return match ($reference->id) {
            'dice' => OptionSet::of(OptionDefinition::text('engraving', 'Engraving', TextConstraints::of(12, characters: '\p{L} '), supplement: Money::of(300, $currency))),
            'loot' => OptionSet::of(OptionDefinition::draw('content', 'Content', 'box')),
            default => OptionSet::none(),
        };
    }
}
