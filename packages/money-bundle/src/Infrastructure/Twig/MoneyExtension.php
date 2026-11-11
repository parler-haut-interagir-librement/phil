<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Infrastructure\Twig;

use Locale;
use NumberFormatter;
use Phil\Money\Money;
use Phil\Money\SchemaOrg;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * `format_money`, the `Money` counterpart of Twig's `format_currency`: `{{ price|format_money }}` prints
 * `1 234,50 $` in `fr_CA` and `CA$1,234.50` in `en`, `{{ price|format_money('en_CA') }}` in a given locale. No
 * money prints nothing.
 *
 * The digits go through a float on their way to ICU, exact up to 2^53 minor units — 90 trillion dollars. One ICU
 * formatter is built per locale and kept for the life of the extension, so a listing of a hundred prices builds
 * one, not a hundred. In a worker that lives across requests, the formatters of 64 locales are kept at most: past
 * them, they are built again, so that locales taken from requests cannot grow the memory without end.
 */
final class MoneyExtension
{
    /** The locales whose formatters are kept at most. */
    private const int KEPT = 64;

    /**
     * @var array<string, NumberFormatter>
     */
    private array $formatters = [];

    #[AsTwigFilter(name: 'format_money')]
    public function formatMoney(?Money $money, ?string $locale = null): string
    {
        if (!$money instanceof Money) {
            return '';
        }

        $locale ??= Locale::getDefault();

        if (!isset($this->formatters[$locale]) && \count($this->formatters) >= self::KEPT) {
            $this->formatters = [];
        }

        $this->formatters[$locale] ??= new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return (string) $this->formatters[$locale]->formatCurrency((float) $money->toDecimal(), $money->currency->code);
    }

    /**
     * The amount as a schema.org `MonetaryAmount`; `null` without one.
     *
     * @return array{'@type': 'MonetaryAmount', currency: string, value: string}|null
     */
    #[AsTwigFunction(name: 'money_schema')]
    public function schema(?Money $money): ?array
    {
        return $money instanceof Money ? SchemaOrg::amount($money) : null;
    }

    /**
     * The `price` and `priceCurrency` of a schema.org `Offer`; `null` without an amount.
     *
     * @return array{price: string, priceCurrency: string}|null
     */
    #[AsTwigFunction(name: 'price_schema')]
    public function price(?Money $money): ?array
    {
        return $money instanceof Money ? SchemaOrg::price($money) : null;
    }
}
