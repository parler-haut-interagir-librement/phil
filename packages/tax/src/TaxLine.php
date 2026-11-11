<?php

declare(strict_types=1);

namespace Phil\Tax;

use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;

/**
 * One line of a tax breakdown: a rate, the base it applies to, and the amount it yields.
 *
 * The amount is usually computed by `TaxRate::taxOn()`, but it may also come from an external tax engine,
 * in which case the line only records the breakdown that engine returned.
 */
final readonly class TaxLine
{
    private function __construct(
        public TaxRate $rate,
        public Money $base,
        public Money $amount,
    ) {
    }

    public static function computed(TaxRate $rate, Money $base): self
    {
        return new self($rate, $base, $rate->taxOn($base));
    }

    public static function reported(TaxRate $rate, Money $base, Money $amount): self
    {
        if (!$base->currency->equals($amount->currency)) {
            throw CurrencyMismatch::between($base->currency, $amount->currency);
        }

        return new self($rate, $base, $amount);
    }

    public function reversed(): self
    {
        return new self($this->rate, $this->base->negate(), $this->amount->negate());
    }
}
