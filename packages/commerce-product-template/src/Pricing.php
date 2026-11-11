<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate;

use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\Money\Money;

/**
 * The price of each product a template makes: a base price, replaced by the price of a value on the one axis that
 * prices — the size, most often —, plus the supplements of the values of any axis — a colour, a version.
 */
final readonly class Pricing
{
    /**
     * @param array<string, array<array-key, Money>> $prices      by axis code then value code, a code of digits keyed
     *                                                            as an integer; one axis at most
     * @param array<string, array<array-key, Money>> $supplements by axis code then value code, a code of digits keyed
     *                                                            as an integer
     */
    private function __construct(
        public Money $base,
        public array $prices,
        public array $supplements,
    ) {
    }

    public static function of(Money $base): self
    {
        return new self(self::checked($base, $base, 'The base price'), [], []);
    }

    /**
     * The price of the products that have this value, instead of the base price; every price is on the same axis.
     */
    public function withPrice(string $axis, string $value, Money $price): self
    {
        if ([] !== $this->prices && !isset($this->prices[$axis])) {
            throw InvalidDefinition::because(\sprintf('The prices are on the axis %s; the axis %s adds supplements.', array_key_first($this->prices), $axis));
        }

        $prices = $this->prices;
        $prices[$axis][$value] = self::checked($this->base, $price, \sprintf('The price of %s %s', $axis, $value));

        return new self($this->base, $prices, $this->supplements);
    }

    /**
     * What the products that have this value add to their price.
     */
    public function withSupplement(string $axis, string $value, Money $supplement): self
    {
        $supplements = $this->supplements;
        $supplements[$axis][$value] = self::checked($this->base, $supplement, \sprintf('The supplement of %s %s', $axis, $value));

        return new self($this->base, $this->prices, $supplements);
    }

    /**
     * The price of the combination: the price of its value on the pricing axis, else the base, plus its supplements.
     */
    public function priceOf(Combination $combination): Money
    {
        $price = $this->base;

        foreach ($combination->values as $axis => $value) {
            $price = $this->prices[$axis][$value->code] ?? $price;
        }

        foreach ($combination->values as $axis => $value) {
            $supplement = $this->supplements[$axis][$value->code] ?? null;

            if ($supplement instanceof Money) {
                $price = $price->add($supplement);
            }
        }

        return $price;
    }

    /**
     * @internal the axes a template checks the pricing against
     *
     * @param array<string, Axis> $axes by code
     */
    public function checkAxes(array $axes): void
    {
        foreach (['price' => $this->prices, 'supplement' => $this->supplements] as $what => $byAxis) {
            foreach ($byAxis as $axis => $values) {
                foreach (array_keys($values) as $value) {
                    if (!isset($axes[$axis]) || !$axes[$axis]->has((string) $value)) {
                        throw InvalidDefinition::because(\sprintf('The pricing gives a %s to %s %s, which the template does not have.', $what, $axis, $value));
                    }
                }
            }
        }
    }

    private static function checked(Money $base, Money $amount, string $what): Money
    {
        if (!$amount->currency->equals($base->currency)) {
            throw InvalidDefinition::because(\sprintf('%s counts in %s, the currency of the base price, not in %s.', $what, $base->currency->code, $amount->currency->code));
        }

        if ($amount->isNegative()) {
            throw InvalidDefinition::because(\sprintf('%s is never negative.', $what));
        }

        return $amount;
    }
}
