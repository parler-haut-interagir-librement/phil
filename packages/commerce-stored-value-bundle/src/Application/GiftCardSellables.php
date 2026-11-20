<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application;

use Override;
use Phil\CommerceBundle\Application\Port\SellableResolver;
use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\Money\Currency;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;

/**
 * Sells gift cards: the sellable type `gift_card`, whose identifier is the value, `CAD:5000` for fifty dollars. The
 * value is the price, and the card is issued at it whatever the line ends up costing; its tax category is configured, since selling a gift card is not, in most places, a taxable
 * supply — spending it is.
 */
final readonly class GiftCardSellables implements SellableResolver
{
    public const string TYPE = 'gift_card';

    public function __construct(
        private string $label,
        private string $taxCategory,
        private int $maxValue,
    ) {
    }

    public static function reference(Money $value): SellableReference
    {
        return SellableReference::of(self::TYPE, $value->currency->code.':'.$value->minorUnits);
    }

    #[Override]
    public function supports(string $type): bool
    {
        return self::TYPE === $type;
    }

    /**
     * The face value a reference names, whatever price the order's resolvers gave the line.
     */
    public static function valueOf(SellableReference $reference): ?Money
    {
        if (self::TYPE !== $reference->type || 1 !== preg_match('/^([A-Z]{3}):([1-9]\d{0,17})$/', $reference->id, $match)) {
            return null;
        }

        try {
            return Money::of((int) $match[2], Currency::of($match[1]));
        } catch (MoneyException) {
            return null;
        }
    }

    #[Override]
    public function resolve(SellableReference $reference): ?Sellable
    {
        $value = self::valueOf($reference);

        if (!$value instanceof Money || $value->minorUnits > $this->maxValue) {
            return null;
        }

        return Sellable::of($reference, \sprintf('%s %s %s', $this->label, $value->currency->code, $value->toDecimal()), $value, $this->taxCategory);
    }
}
