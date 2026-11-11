<?php

declare(strict_types=1);

namespace Phil\Money\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Money\SchemaOrg;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaOrg::class)]
final class SchemaOrgTest extends TestCase
{
    public function testAnAmountIsAMonetaryAmountOrAPrice(): void
    {
        $price = Money::ofDecimal('19.95', Currency::of('CAD'));

        self::assertSame(['@type' => 'MonetaryAmount', 'currency' => 'CAD', 'value' => '19.95'], SchemaOrg::amount($price));
        self::assertSame(['price' => '19.95', 'priceCurrency' => 'CAD'], SchemaOrg::price($price));
        self::assertSame(['price' => '1500', 'priceCurrency' => 'JPY'], SchemaOrg::price(Money::of(1500, Currency::of('JPY'))));
    }
}
