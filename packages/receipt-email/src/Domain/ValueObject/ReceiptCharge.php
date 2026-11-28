<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\ValueObject;

use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Guard;

/**
 * An amount the receipt adds or takes off besides the lines and the taxes — shipping, a fee, a discount, a tip —,
 * with its label.
 */
final readonly class ReceiptCharge
{
    private function __construct(
        public string $label,
        public Money $amount,
    ) {
    }

    public static function of(string $label, Money $amount): self
    {
        return new self(Guard::text($label, 'The label of a charge'), $amount);
    }
}
