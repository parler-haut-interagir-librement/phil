<?php

declare(strict_types=1);

namespace Phil\Money\Tests\Unit;

use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Exception\InvalidAmount;
use Phil\Money\Exception\MoneyException;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Money::class)]
#[CoversClass(CurrencyMismatch::class)]
#[CoversClass(InvalidAmount::class)]
final class MoneyTest extends TestCase
{
    public function testAnAmountKeepsItsUnitsAndCurrency(): void
    {
        $money = Money::of(1_250, self::cad());

        self::assertSame(1_250, $money->minorUnits);
        self::assertSame('CAD', $money->currency->code);
        self::assertSame(0, Money::zero(self::cad())->minorUnits);
    }

    public function testAmountsInOneCurrencyAddAndSubtract(): void
    {
        $cad = self::cad();

        self::assertSame(2_000, Money::of(1_250, $cad)->add(Money::of(750, $cad))->minorUnits);
        self::assertSame(-750, Money::of(1_250, $cad)->subtract(Money::of(2_000, $cad))->minorUnits);
        self::assertSame(2_250, Money::sum(Money::of(1_250, $cad), Money::of(750, $cad), Money::of(250, $cad))->minorUnits);
        self::assertSame(1_250, Money::sum(Money::of(1_250, $cad))->minorUnits);
    }

    public function testNegationAbsoluteValueAndSign(): void
    {
        $cad = self::cad();

        self::assertSame(-500, Money::of(500, $cad)->negate()->minorUnits);
        self::assertSame(500, Money::of(-500, $cad)->abs()->minorUnits);
        self::assertSame(500, Money::of(500, $cad)->abs()->minorUnits);
        self::assertSame('CAD', Money::of(-500, $cad)->abs()->currency->code);
        self::assertSame('CAD', Money::of(500, $cad)->negate()->currency->code);
        self::assertTrue(Money::zero($cad)->isZero());
        self::assertFalse(Money::of(1, $cad)->isZero());
        self::assertTrue(Money::of(1, $cad)->isPositive());
        self::assertFalse(Money::zero($cad)->isPositive());
        self::assertTrue(Money::of(-1, $cad)->isNegative());
        self::assertFalse(Money::zero($cad)->isNegative());
    }

    public function testMultiplication(): void
    {
        self::assertSame(-3_750, Money::of(1_250, self::cad())->multiply(-3)->minorUnits);
        self::assertSame('CAD', Money::of(1_250, self::cad())->multiply(2)->currency->code);
    }

    public function testComparison(): void
    {
        $cad = self::cad();

        self::assertSame(-1, Money::of(1, $cad)->compare(Money::of(2, $cad)));
        self::assertSame(0, Money::of(2, $cad)->compare(Money::of(2, $cad)));
        self::assertSame(1, Money::of(3, $cad)->compare(Money::of(2, $cad)));
        self::assertTrue(Money::of(3, $cad)->isGreaterThan(Money::of(2, $cad)));
        self::assertFalse(Money::of(2, $cad)->isGreaterThan(Money::of(2, $cad)));
        self::assertTrue(Money::of(1, $cad)->isLessThan(Money::of(2, $cad)));
        self::assertFalse(Money::of(2, $cad)->isLessThan(Money::of(2, $cad)));
    }

    public function testEquality(): void
    {
        self::assertTrue(Money::of(100, self::cad())->equals(Money::of(100, self::cad())));
        self::assertFalse(Money::of(100, self::cad())->equals(Money::of(101, self::cad())));
        self::assertFalse(Money::of(100, self::cad())->equals(Money::of(100, Currency::of('USD'))));
    }

    public function testAddingAcrossCurrenciesIsRefused(): void
    {
        try {
            Money::of(100, self::cad())->add(Money::of(100, Currency::of('USD')));
            self::fail('Adding across currencies must throw.');
        } catch (CurrencyMismatch $currencyMismatch) {
            self::assertInstanceOf(MoneyException::class, $currencyMismatch);
            self::assertSame('An amount in CAD cannot be combined with an amount in USD.', $currencyMismatch->getMessage());
        }
    }

    public function testSubtractingAcrossCurrenciesIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        Money::of(100, self::cad())->subtract(Money::of(100, Currency::of('USD')));
    }

    public function testComparingAcrossCurrenciesIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        Money::of(100, self::cad())->compare(Money::of(100, Currency::of('EUR')));
    }

    public function testSummingAcrossCurrenciesIsRefused(): void
    {
        $this->expectException(CurrencyMismatch::class);

        Money::sum(Money::of(100, self::cad()), Money::of(100, Currency::of('EUR')));
    }

    /**
     * @return iterable<string, array{callable(): Money}>
     */
    public static function overflows(): iterable
    {
        yield 'addition' => [static fn (): Money => Money::of(\PHP_INT_MAX, self::cad())->add(Money::of(1, self::cad()))];
        yield 'subtraction' => [static fn (): Money => Money::of(-\PHP_INT_MAX, self::cad())->subtract(Money::of(2, self::cad()))];
        yield 'subtraction to PHP_INT_MIN' => [static fn (): Money => Money::of(-\PHP_INT_MAX, self::cad())->subtract(Money::of(1, self::cad()))];
        yield 'multiplication' => [static fn (): Money => Money::of(\PHP_INT_MAX, self::cad())->multiply(2)];
        yield 'PHP_INT_MIN' => [static fn (): Money => Money::of(\PHP_INT_MIN, self::cad())];
    }

    /**
     * @param callable(): Money $operation
     */
    #[DataProvider('overflows')]
    public function testAnOverflowIsRefusedRatherThanTurnedIntoAFloat(callable $operation): void
    {
        $this->expectExceptionObject(InvalidAmount::outOfRange());

        $operation();
    }

    public function testTheLargestAmountsAreKept(): void
    {
        self::assertSame(-\PHP_INT_MAX, Money::of(\PHP_INT_MAX, self::cad())->negate()->minorUnits);
        self::assertSame(\PHP_INT_MAX, Money::of(-\PHP_INT_MAX, self::cad())->abs()->minorUnits);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function decimals(): iterable
    {
        yield 'cents' => ['12.34', 'CAD', 1_234];
        yield 'one decimal' => ['12.5', 'CAD', 1_250];
        yield 'no decimal' => ['12', 'CAD', 1_200];
        yield 'negative' => ['-0.05', 'CAD', -5];
        yield 'negative zero' => ['-0', 'CAD', 0];
        yield 'leading zeros' => ['0012.30', 'CAD', 1_230];
        yield 'trailing zeros beyond the precision' => ['12.3400', 'CAD', 1_234];
        yield 'zero decimals for the yen' => ['1200', 'JPY', 1_200];
        yield 'trailing zeros for the yen' => ['1200.00', 'JPY', 1_200];
        yield 'three decimals' => ['1.005', 'BHD', 1_005];
        yield 'the largest amount' => ['92233720368547758.07', 'CAD', \PHP_INT_MAX];
        yield 'the largest amount with leading zeros' => ['0092233720368547758.07', 'CAD', \PHP_INT_MAX];
        yield 'the smallest amount' => ['-92233720368547758.07', 'CAD', -\PHP_INT_MAX];
    }

    #[DataProvider('decimals')]
    public function testADecimalIsReadToTheCurrencysPrecision(string $decimal, string $currency, int $minorUnits): void
    {
        self::assertSame($minorUnits, Money::ofDecimal($decimal, Currency::of($currency))->minorUnits);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notDecimals(): iterable
    {
        yield 'empty' => [''];
        yield 'a comma' => ['12,34'];
        yield 'a plus sign' => ['+12'];
        yield 'a dot alone' => ['12.'];
        yield 'no integer part' => ['.5'];
        yield 'an exponent' => ['1e3'];
        yield 'spaces' => [' 12'];
        yield 'a trailing newline' => ["12\n"];
        yield 'a leading letter' => ['a12'];
    }

    #[DataProvider('notDecimals')]
    public function testAnythingElseIsNotADecimal(string $decimal): void
    {
        $this->expectExceptionObject(InvalidAmount::notADecimal($decimal));

        Money::ofDecimal($decimal, self::cad());
    }

    public function testADecimalFinerThanTheCurrencyIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidAmount('"12.345" has more decimals than CAD counts (2).'));

        Money::ofDecimal('12.345', self::cad());
    }

    public function testADecimalFinerThanTheYenIsRefused(): void
    {
        $this->expectExceptionObject(new InvalidAmount('"12.5" has more decimals than JPY counts (0).'));

        Money::ofDecimal('12.5', Currency::of('JPY'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function outOfRangeDecimals(): iterable
    {
        yield 'one unit too many' => ['92233720368547758.08'];
        yield 'one digit too many' => ['100000000000000000.00'];
        yield 'negative' => ['-92233720368547758.08'];
    }

    #[DataProvider('outOfRangeDecimals')]
    public function testADecimalOutsideTheNativeIntegerIsRefused(string $decimal): void
    {
        $this->expectExceptionObject(InvalidAmount::outOfRange());

        Money::ofDecimal($decimal, self::cad());
    }

    /**
     * @return iterable<string, array{int, string, string}>
     */
    public static function printedDecimals(): iterable
    {
        yield 'cents' => [1_234, 'CAD', '12.34'];
        yield 'less than a unit' => [5, 'CAD', '0.05'];
        yield 'negative' => [-5, 'CAD', '-0.05'];
        yield 'negative above a unit' => [-1_234, 'CAD', '-12.34'];
        yield 'zero' => [0, 'CAD', '0.00'];
        yield 'the yen' => [1_200, 'JPY', '1200'];
        yield 'the negative yen' => [-1_200, 'JPY', '-1200'];
        yield 'three decimals' => [1_005, 'BHD', '1.005'];
        yield 'the largest amount' => [\PHP_INT_MAX, 'CAD', '92233720368547758.07'];
    }

    #[DataProvider('printedDecimals')]
    public function testTheDecimalIsPrintedToTheCurrencysPrecision(int $minorUnits, string $currency, string $decimal): void
    {
        self::assertSame($decimal, Money::of($minorUnits, Currency::of($currency))->toDecimal());
    }

    /**
     * @return iterable<string, array{int, list<int>, list<int>}>
     */
    public static function allocations(): iterable
    {
        yield 'thirds' => [100, [1, 1, 1], [34, 33, 33]];
        yield 'exact shares' => [100, [1, 2, 2], [20, 40, 40]];
        yield 'exact shares of an uneven split' => [10, [3, 3, 4], [3, 3, 4]];
        yield 'a remainder to a later part' => [5, [1, 3], [1, 4]];
        yield 'two remainders' => [11, [1, 1, 1], [4, 4, 3]];
        yield 'a tie goes to the earlier part' => [5, [3, 7], [2, 3]];
        yield 'a zero ratio receives nothing' => [5, [0, 1, 1], [0, 3, 2]];
        yield 'negative' => [-100, [1, 1, 1], [-34, -33, -33]];
        yield 'negative, the largest remainder to a later part' => [-100, [1, 1, 5], [-14, -14, -72]];
        yield 'zero' => [0, [1, 1], [0, 0]];
        yield 'one part' => [7, [4], [7]];
        yield 'the largest remainder to a later part' => [100, [1, 1, 5], [14, 14, 72]];
    }

    /**
     * @param list<int> $ratios
     * @param list<int> $expected
     */
    #[DataProvider('allocations')]
    public function testAnAllocationNeverLosesAUnit(int $amount, array $ratios, array $expected): void
    {
        $parts = Money::of($amount, self::cad())->allocate(...$ratios);

        self::assertSame($expected, array_map(static fn (Money $part): int => $part->minorUnits, $parts));
        self::assertSame(['CAD'], array_values(array_unique(array_map(static fn (Money $part): string => $part->currency->code, $parts))));
    }

    public function testAllocationKeysAreAList(): void
    {
        $parts = Money::of(100, self::cad())->allocate(...['a' => 1, 'b' => 3]);

        self::assertSame([0, 1], array_keys($parts));
    }

    public function testASplitGivesTheEarliestPartsTheRemainder(): void
    {
        self::assertSame([34, 33, 33], array_map(static fn (Money $part): int => $part->minorUnits, Money::of(100, self::cad())->split(3)));
        self::assertSame([100], array_map(static fn (Money $part): int => $part->minorUnits, Money::of(100, self::cad())->split(1)));
        self::assertSame([4, 4, 3], array_map(static fn (Money $part): int => $part->minorUnits, Money::of(11, self::cad())->split(3)));
        self::assertSame([-34, -33, -33], array_map(static fn (Money $part): int => $part->minorUnits, Money::of(-100, self::cad())->split(3)));
        self::assertSame(['CAD'], array_values(array_unique(array_map(static fn (Money $part): string => $part->currency->code, Money::of(100, self::cad())->split(3)))));
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedAllocations(): iterable
    {
        yield 'no ratio' => [static fn (): array => Money::of(100, self::cad())->allocate(), 'An amount is allocated by one ratio or more.'];
        yield 'a negative ratio' => [static fn (): array => Money::of(100, self::cad())->allocate(1, -1), 'An allocation ratio is zero or more; -1 is not.'];
        yield 'zero ratios' => [static fn (): array => Money::of(100, self::cad())->allocate(0, 0), 'The allocation ratios add up to zero.'];
        yield 'ratios out of range' => [static fn (): array => Money::of(100, self::cad())->allocate(\PHP_INT_MAX, 1), 'The amount leaves the range of a native integer.'];
        yield 'a product out of range' => [static fn (): array => Money::of(\PHP_INT_MAX, self::cad())->allocate(2, 1), 'The amount leaves the range of a native integer.'];
        yield 'no part' => [static fn (): array => Money::of(100, self::cad())->split(0), 'An amount is split into one part or more; 0 is not.'];
    }

    /**
     * @param callable(): mixed $allocation
     */
    #[DataProvider('refusedAllocations')]
    public function testARefusedAllocation(callable $allocation, string $message): void
    {
        $this->expectExceptionObject(InvalidAmount::because($message));

        $allocation();
    }

    private static function cad(): Currency
    {
        return Currency::of('CAD');
    }
}
