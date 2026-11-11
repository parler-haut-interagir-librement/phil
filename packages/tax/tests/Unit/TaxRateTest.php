<?php

declare(strict_types=1);

namespace Phil\Tax\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\Tax\Exception\InvalidTaxRate;
use Phil\Tax\Exception\TaxException;
use Phil\Tax\Exception\UntaxableBase;
use Phil\Tax\TaxRate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxRate::class)]
#[CoversClass(InvalidTaxRate::class)]
#[CoversClass(UntaxableBase::class)]
final class TaxRateTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function taxes(): iterable
    {
        yield '5 % of 10.00' => [50_000, 1_000, 50];
        yield '9.975 % of 10.00, 99.75 rounds up' => [99_750, 1_000, 100];
        yield '5 % of 19.99, 99.95 rounds up' => [50_000, 1_999, 100];
        yield '9.975 % of 19.99, 199.40 rounds down' => [99_750, 1_999, 199];
        yield 'exactly half rounds away from zero' => [500_000, 1, 1];
        yield 'just under half rounds down' => [499_999, 1, 0];
        yield 'zero rate' => [0, 1_000, 0];
        yield 'full rate' => [1_000_000, 1_234, 1_234];
        yield 'zero base' => [50_000, 0, 0];
    }

    #[DataProvider('taxes')]
    public function testTheTaxIsRoundedHalfAwayFromZero(int $ppm, int $base, int $expected): void
    {
        self::assertSame($expected, TaxRate::of('GST', $ppm)->taxOn(Money::of($base, Currency::of('CAD')))->minorUnits);
    }

    #[DataProvider('taxes')]
    public function testTheTaxOnANegatedBaseIsTheNegatedTax(int $ppm, int $base, int $expected): void
    {
        self::assertSame(-$expected, TaxRate::of('GST', $ppm)->taxOn(Money::of(-$base, Currency::of('CAD')))->minorUnits);
    }

    public function testTheTaxIsInTheCurrencyOfTheBase(): void
    {
        self::assertSame('EUR', TaxRate::of('VAT', 200_000)->taxOn(Money::of(100, Currency::of('EUR')))->currency->code);
    }

    public function testTheTaxCountsInTheUnitsOfTheCurrency(): void
    {
        self::assertSame(100, TaxRate::of('CT', 100_000)->taxOn(Money::of(1_000, Currency::of('JPY')))->minorUnits);
        self::assertSame(1_250, TaxRate::of('VAT', 100_000)->taxOn(Money::of(12_500, Currency::of('BHD')))->minorUnits);
    }

    public function testTheLargestTaxableBaseIsTaxed(): void
    {
        $base = intdiv(\PHP_INT_MAX - 1_000_000, 1_000_000);

        self::assertSame($base, TaxRate::of('ALL', 1_000_000)->taxOn(Money::of($base, Currency::of('CAD')))->minorUnits);
        self::assertSame(-$base, TaxRate::of('ALL', 1_000_000)->taxOn(Money::of(-$base, Currency::of('CAD')))->minorUnits);
    }

    public function testABaseTooLargeToTaxIsRefused(): void
    {
        $this->expectException(UntaxableBase::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The taxable base is too large to be taxed in a native integer: %d minor units at most.', intdiv(\PHP_INT_MAX - 1_000_000, 1_000_000)));

        TaxRate::of('GST', 50_000)->taxOn(Money::of(intdiv(\PHP_INT_MAX - 1_000_000, 1_000_000) + 1, Currency::of('CAD')));
    }

    public function testANegatedBaseTooLargeToTaxIsRefused(): void
    {
        $this->expectException(UntaxableBase::class);

        TaxRate::of('GST', 50_000)->taxOn(Money::of(-intdiv(\PHP_INT_MAX - 1_000_000, 1_000_000) - 1, Currency::of('CAD')));
    }

    public function testTheBoundsOfARateAreAccepted(): void
    {
        self::assertSame(0, TaxRate::of('Z', 0)->partsPerMillion);
        self::assertSame(1_000_000, TaxRate::of('A1_B-C', 1_000_000)->partsPerMillion);
        self::assertSame(str_repeat('A', 32), TaxRate::of(str_repeat('A', 32), 1)->code);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidRates(): iterable
    {
        yield 'negative' => ['GST', -1, 'A tax rate lies between 0 and 1000000 parts per million; -1 does not.'];
        yield 'above one hundred percent' => ['GST', 1_000_001, 'A tax rate lies between 0 and 1000000 parts per million; 1000001 does not.'];
        yield 'empty code' => ['', 50_000, '"" is not a tax rate code: up to 32 uppercase letters, digits, "_" or "-", starting with a letter.'];
        yield 'lowercase code' => ['gst', 50_000, '"gst" is not a tax rate code'];
        yield 'code starting with a digit' => ['5GST', 50_000, '"5GST" is not a tax rate code'];
        yield 'code too long' => [str_repeat('A', 33), 50_000, 'is not a tax rate code'];
        yield 'code with a trailing newline' => ["GST\n", 50_000, 'is not a tax rate code'];
    }

    #[DataProvider('invalidRates')]
    public function testAnInvalidRateIsRefused(string $code, int $ppm, string $message): void
    {
        $this->expectException(InvalidTaxRate::class);
        $this->expectExceptionMessageIsOrContains($message);

        TaxRate::of($code, $ppm);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function percentages(): iterable
    {
        yield 'whole' => ['5', 50_000];
        yield 'three decimals' => ['9.975', 99_750];
        yield 'four decimals' => ['0.0001', 1];
        yield 'trailing zeros' => ['13.00', 130_000];
        yield 'zero' => ['0', 0];
        yield 'one hundred' => ['100', 1_000_000];
        yield 'one hundred with decimals' => ['100.0000', 1_000_000];
    }

    #[DataProvider('percentages')]
    public function testARateIsReadFromItsPercentage(string $percent, int $ppm): void
    {
        $rate = TaxRate::ofPercent('QST', $percent);

        self::assertSame('QST', $rate->code);
        self::assertSame($ppm, $rate->partsPerMillion);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function printedPercentages(): iterable
    {
        yield 'whole' => [50_000, '5'];
        yield 'three decimals' => [99_750, '9.975'];
        yield 'one part per million' => [1, '0.0001'];
        yield 'a leading zero in the fraction' => [10_500, '1.05'];
        yield 'just under ten' => [99_990, '9.999'];
        yield 'zero' => [0, '0'];
        yield 'one hundred' => [1_000_000, '100'];
    }

    #[DataProvider('printedPercentages')]
    public function testThePercentageIsPrintedWithoutTrailingZeros(int $ppm, string $percent): void
    {
        self::assertSame($percent, TaxRate::of('GST', $ppm)->percent());
        self::assertSame($ppm, TaxRate::ofPercent('GST', $percent)->partsPerMillion);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPercentages(): iterable
    {
        yield 'empty' => [''];
        yield 'negative' => ['-5'];
        yield 'comma' => ['9,975'];
        yield 'five decimals' => ['9.97501'];
        yield 'above one hundred' => ['100.0001'];
        yield 'four digits' => ['1000'];
        yield 'no digit after the point' => ['5.'];
        yield 'no digit before the point' => ['.5'];
        yield 'trailing newline' => ["5\n"];
        yield 'leading space' => [' 5'];
    }

    #[DataProvider('invalidPercentages')]
    public function testAPercentageARateCannotHoldIsRefused(string $percent): void
    {
        $this->expectException(InvalidTaxRate::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a percentage a tax rate holds: digits with up to 4 decimals after ".", from 0 to 100, are expected.', $percent));

        TaxRate::ofPercent('GST', $percent);
    }

    public function testACodeIsCheckedWhenReadFromAPercentage(): void
    {
        $this->expectException(InvalidTaxRate::class);
        $this->expectExceptionMessageIsOrContains('"gst" is not a tax rate code');

        TaxRate::ofPercent('gst', '5');
    }

    public function testTwoRatesAreEqualByCodeAndShare(): void
    {
        self::assertTrue(TaxRate::of('GST', 50_000)->equals(TaxRate::ofPercent('GST', '5')));
        self::assertFalse(TaxRate::of('GST', 50_000)->equals(TaxRate::of('HST', 50_000)));
        self::assertFalse(TaxRate::of('GST', 50_000)->equals(TaxRate::of('GST', 50_001)));
    }

    public function testEveryExceptionIsATaxException(): void
    {
        self::assertInstanceOf(TaxException::class, InvalidTaxRate::code(''));
        self::assertInstanceOf(TaxException::class, UntaxableBase::tooLarge(1));
    }
}
