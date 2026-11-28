<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\ValueObject;

use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Guard;

/**
 * One tax as the receipt shows it: its rate code — `GST`, `QST` —, its amount, and whether the prices already
 * included it rather than its being added to the total.
 */
final readonly class TaxAmount
{
    private function __construct(
        public string $code,
        public Money $amount,
        public bool $included,
    ) {
    }

    public static function of(string $code, Money $amount, bool $included = false): self
    {
        return new self(Guard::text($code, 'A tax code', Guard::MAX_IDENTIFIER), $amount, $included);
    }
}
