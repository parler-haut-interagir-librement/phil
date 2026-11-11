<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplate\Tests\Unit;

use Phil\CommerceProductTemplate\Axis;
use Phil\CommerceProductTemplate\AxisValue;
use Phil\CommerceProductTemplate\Combination;
use Phil\CommerceProductTemplate\Exception\InvalidDefinition;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\Pricing;
use Phil\CommerceProductTemplate\Tests\Unit\Fixture\Shirts;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pricing::class)]
final class PricingTest extends TestCase
{
    public function testACombinationTakesThePriceOfItsValueOrTheBaseThenItsSupplements(): void
    {
        $pricing = Pricing::of(Shirts::cad(2_500))
            ->withPrice('size', '2X', Shirts::cad(2_800))
            ->withPrice('size', '3X', Shirts::cad(3_000))
            ->withSupplement('colour', 'GL', Shirts::cad(300))
            ->withSupplement('version', 'L', Shirts::cad(1_000));

        self::assertSame(2_500, $pricing->priceOf($this->combination('M', 'BK', 'O'))->minorUnits);
        self::assertSame(2_800, $pricing->priceOf($this->combination('2X', 'BK', 'O'))->minorUnits);
        self::assertSame(3_000, $pricing->priceOf($this->combination('3X', 'BK', 'O'))->minorUnits);
        self::assertSame(4_300, $pricing->priceOf($this->combination('3X', 'GL', 'L'))->minorUnits);
        self::assertSame(2_800, $pricing->priceOf($this->combination('M', 'GL', 'O'))->minorUnits);
        self::assertSame(2_500, $pricing->base->minorUnits);
        self::assertSame(['size' => ['2X', '3X']], array_map(array_keys(...), $pricing->prices));
        self::assertSame(['colour' => ['GL'], 'version' => ['L']], array_map(array_keys(...), $pricing->supplements));
    }

    public function testAZeroPriceAndSupplementAreAllowed(): void
    {
        $pricing = Pricing::of(Shirts::cad(0))->withPrice('size', 'M', Shirts::cad(0))->withSupplement('colour', 'BK', Shirts::cad(0));

        self::assertTrue($pricing->priceOf($this->combination('M', 'BK', 'O'))->isZero());
    }

    public function testThePricesAreOnOneAxis(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('The prices are on the axis size; the axis colour adds supplements.'));

        Pricing::of(Shirts::cad(2_500))->withPrice('size', '2X', Shirts::cad(2_800))->withPrice('colour', 'BK', Shirts::cad(2_800));
    }

    public function testEveryAmountCountsInTheCurrencyOfTheBase(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('The supplement of colour GL counts in CAD, the currency of the base price, not in USD.'));

        Pricing::of(Shirts::cad(2_500))->withSupplement('colour', 'GL', Money::of(300, Currency::of('USD')));
    }

    public function testAPriceInAnotherCurrencyIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('The price of size 2X counts in CAD, the currency of the base price, not in USD.'));

        Pricing::of(Shirts::cad(2_500))->withPrice('size', '2X', Money::of(300, Currency::of('USD')));
    }

    public function testNoAmountIsNegative(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('The base price is never negative.'));

        Pricing::of(Shirts::cad(-1));
    }

    public function testNoSupplementIsNegative(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('The supplement of colour GL is never negative.'));

        Pricing::of(Shirts::cad(1))->withSupplement('colour', 'GL', Shirts::cad(-1));
    }

    public function testTheWithersLeaveTheOriginalAsItWas(): void
    {
        $pricing = Pricing::of(Shirts::cad(2_500));
        $pricing->withPrice('size', '2X', Shirts::cad(2_800));
        $pricing->withSupplement('colour', 'GL', Shirts::cad(300));

        self::assertSame([], $pricing->prices);
        self::assertSame([], $pricing->supplements);
    }

    public function testTheAxesItNamesAreCheckedByTheTemplate(): void
    {
        $axes = ['size' => Axis::of('size', Label::of(['en' => 'Size']), AxisValue::of('0', Label::of(['en' => 'XS'])), AxisValue::of('2X', Label::of(['en' => '2XL'])))];
        Pricing::of(Shirts::cad(1))->withPrice('size', '0', Shirts::cad(2))->withSupplement('size', '2X', Shirts::cad(1))->checkAxes($axes);

        try {
            Pricing::of(Shirts::cad(1))->withPrice('size', '4X', Shirts::cad(2))->checkAxes($axes);
            self::fail('A price of a value the axis lacks is refused.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame('The pricing gives a price to size 4X, which the template does not have.', $invalidDefinition->getMessage());
        }

        $this->expectExceptionObject(InvalidDefinition::because('The pricing gives a supplement to colour GL, which the template does not have.'));

        Pricing::of(Shirts::cad(1))->withSupplement('colour', 'GL', Shirts::cad(2))->checkAxes($axes);
    }

    private function combination(string $size, string $colour, string $version): Combination
    {
        return new Combination([
            'size' => AxisValue::of($size, Label::of(['en' => $size])),
            'colour' => AxisValue::of($colour, Label::of(['en' => $colour])),
            'version' => AxisValue::of($version, Label::of(['en' => $version])),
        ]);
    }
}
