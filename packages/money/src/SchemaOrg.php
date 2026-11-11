<?php

declare(strict_types=1);

namespace Phil\Money;

/**
 * Amounts as schema.org reads them: a `MonetaryAmount`, or the `price` and `priceCurrency` of an `Offer`. The value
 * is the exact decimal, as text: a float would round a price.
 */
final class SchemaOrg
{
    /**
     * @return array{'@type': 'MonetaryAmount', currency: string, value: string}
     */
    public static function amount(Money $money): array
    {
        return ['@type' => 'MonetaryAmount', 'currency' => $money->currency->code, 'value' => $money->toDecimal()];
    }

    /**
     * @return array{price: string, priceCurrency: string}
     */
    public static function price(Money $money): array
    {
        return ['price' => $money->toDecimal(), 'priceCurrency' => $money->currency->code];
    }
}
