<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Sales;

use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * The sales of one value of a dimension in one currency: the units sold and returned, the gross sales, the discounts
 * and the returns. Amounts in two currencies are two totals, never one.
 */
final readonly class SalesTotals
{
    public function __construct(
        public string $key,
        public string $label,
        public Currency $currency,
        public int $sold,
        public int $returned,
        public Money $gross,
        public Money $discounts,
        public Money $returns,
    ) {
    }

    public static function empty(string $key, string $label, Currency $currency): self
    {
        $zero = Money::zero($currency);

        return new self($key, $label, $currency, 0, 0, $zero, $zero, $zero);
    }

    /**
     * The net sales: the gross sales less the discounts and the returns, without taxes or tips; negative when a period
     * gave back more than it sold.
     */
    public function net(): Money
    {
        return $this->gross->subtract($this->discounts)->subtract($this->returns);
    }

    public function withSale(int $quantity, Money $gross, Money $discounts): self
    {
        return new self($this->key, $this->label, $this->currency, $this->sold + $quantity, $this->returned, $this->gross->add($gross), $this->discounts->add($discounts), $this->returns);
    }

    public function withReturn(int $quantity, Money $amount): self
    {
        return new self($this->key, $this->label, $this->currency, $this->sold, $this->returned + $quantity, $this->gross, $this->discounts, $this->returns->add($amount));
    }
}
