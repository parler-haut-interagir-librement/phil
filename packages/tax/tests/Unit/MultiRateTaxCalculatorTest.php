<?php

declare(strict_types=1);

namespace Phil\Tax\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\Exception\UntaxableBase;
use Phil\Tax\MultiRateTaxCalculator;
use Phil\Tax\TaxBreakdown;
use Phil\Tax\TaxLine;
use Phil\Tax\TaxRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MultiRateTaxCalculator::class)]
final class MultiRateTaxCalculatorTest extends TestCase
{
    /**
     * Both rates apply to the same base: the provincial tax is 9.975 % of 19.99, not of 19.99 plus the
     * federal tax. Compounding would give 209 instead of 199.
     */
    public function testEveryRateAppliesToTheSameBaseWithoutCompounding(): void
    {
        $base = Money::of(1_999, Currency::of('CAD'));
        $breakdown = new MultiRateTaxCalculator()->calculate($base, [TaxRate::of('GST', 50_000), TaxRate::of('QST', 99_750)]);

        self::assertTrue($breakdown->base->equals($base));
        self::assertSame(['GST', 'QST'], array_map(static fn (TaxLine $line): string => $line->rate->code, $breakdown->lines));
        self::assertSame([100, 199], array_map(static fn (TaxLine $line): int => $line->amount->minorUnits, $breakdown->lines));
        self::assertSame(2_298, $breakdown->totalWithTax()->minorUnits);
    }

    /**
     * Each line is rounded on its own, as the receipt shows it, rather than the sum of the rates being
     * rounded once: 0.5 + 0.5 would round to 1 once, and to 2 line by line.
     */
    public function testEachLineIsRoundedOnItsOwn(): void
    {
        $breakdown = new MultiRateTaxCalculator()->calculate(
            Money::of(10, Currency::of('CAD')),
            [TaxRate::of('A', 50_000), TaxRate::of('B', 50_000)],
        );

        self::assertSame(2, $breakdown->totalTax()->minorUnits);
    }

    public function testNoRateMeansNoLine(): void
    {
        self::assertSame([], new MultiRateTaxCalculator()->calculate(Money::of(1_000, Currency::of('CAD')), [])->lines);
    }

    /**
     * Ten dollars with both Quebec taxes in: the taxes are computed on 8.70, and the cent their rounding leaves stays in
     * the base, so the price paid is still ten dollars.
     */
    public function testTheTaxesIncludedInAPriceAreTakenOutOfIt(): void
    {
        $price = Money::of(1_000, Currency::of('CAD'));
        $breakdown = new MultiRateTaxCalculator()->extract($price, [TaxRate::of('GST', 50_000), TaxRate::of('QST', 99_750)]);

        self::assertSame(869, $breakdown->base->minorUnits);
        self::assertSame(['GST', 'QST'], array_map(static fn (TaxLine $line): string => $line->rate->code, $breakdown->lines));
        self::assertSame([44, 87], array_map(static fn (TaxLine $line): int => $line->amount->minorUnits, $breakdown->lines));
        self::assertSame([869, 869], array_map(static fn (TaxLine $line): int => $line->base->minorUnits, $breakdown->lines));
        self::assertTrue($breakdown->totalWithTax()->equals($price));
        self::assertSame(131, $breakdown->totalTax()->minorUnits);
    }

    public function testAnExactPriceLeavesAnExactBase(): void
    {
        $breakdown = new MultiRateTaxCalculator()->extract(Money::of(1_050, Currency::of('CAD')), [TaxRate::of('GST', 50_000)]);

        self::assertSame([1_000, 50], [$breakdown->base->minorUnits, $breakdown->totalTax()->minorUnits]);
    }

    /**
     * 0.21 at 5 % is 0.20 before taxes — 20.476 rounds down —, and 0.22 is 0.21 — 20.952 rounds up. A half rounds away
     * from zero: 0.21 at 100 % is 0.105, so 0.11 before taxes, a tax of 0.11 and a base of 0.10.
     */
    public function testTheBaseBeforeTaxesIsRoundedHalfAwayFromZero(): void
    {
        $calculator = new MultiRateTaxCalculator();
        $five = [TaxRate::of('GST', 50_000)];

        self::assertSame(20, $calculator->extract(Money::of(21, Currency::of('CAD')), $five)->base->minorUnits);
        self::assertSame(21, $calculator->extract(Money::of(22, Currency::of('CAD')), $five)->base->minorUnits);
        self::assertSame([10, 10], $this->baseAndTax($calculator->extract(Money::of(20, Currency::of('CAD')), [TaxRate::of('ALL', 1_000_000)])));
        self::assertSame([10, 11], $this->baseAndTax($calculator->extract(Money::of(21, Currency::of('CAD')), [TaxRate::of('ALL', 1_000_000)])));
        self::assertSame([-10, -11], $this->baseAndTax($calculator->extract(Money::of(-21, Currency::of('CAD')), [TaxRate::of('ALL', 1_000_000)])));
    }

    /**
     * @return array{int, int}
     */
    private function baseAndTax(TaxBreakdown $breakdown): array
    {
        return [$breakdown->base->minorUnits, $breakdown->totalTax()->minorUnits];
    }

    /**
     * A refund takes out exactly the taxes the sale included, negated.
     */
    public function testANegativePriceGivesTheNegatedTaxes(): void
    {
        $rates = [TaxRate::of('GST', 50_000), TaxRate::of('QST', 99_750)];
        $breakdown = new MultiRateTaxCalculator()->extract(Money::of(-1_000, Currency::of('CAD')), $rates);

        self::assertSame([-869, -44, -87], [$breakdown->base->minorUnits, $breakdown->lines[0]->amount->minorUnits, $breakdown->lines[1]->amount->minorUnits]);
    }

    public function testAPriceWithoutRatesIsAllBase(): void
    {
        $breakdown = new MultiRateTaxCalculator()->extract(Money::of(1_000, Currency::of('CAD')), []);

        self::assertSame([1_000, []], [$breakdown->base->minorUnits, $breakdown->lines]);
    }

    public function testAPriceTooLargeIsRefused(): void
    {
        $rates = [TaxRate::of('GST', 50_000)];
        $largest = intdiv(\PHP_INT_MAX - 1_050_000, 1_000_000);

        self::assertSame($largest, abs(new MultiRateTaxCalculator()->extract(Money::of(-$largest, Currency::of('CAD')), $rates)->totalWithTax()->minorUnits));
        $this->expectException(UntaxableBase::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The taxable base is too large to be taxed in a native integer: %d minor units at most.', $largest));

        new MultiRateTaxCalculator()->extract(Money::of($largest + 1, Currency::of('CAD')), $rates);
    }
}
