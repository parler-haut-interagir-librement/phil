<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\ValueObject\Deposit;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Exception\CurrencyMismatch;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Deposit::class)]
final class DepositTest extends TestCase
{
    /**
     * @return iterable<string, array{Deposit, int, int}>
     */
    public static function deposits(): iterable
    {
        yield 'nothing' => [Deposit::none(), 1_000, 0];
        yield 'a share of zero' => [Deposit::share(0), 1_000, 0];
        yield 'a quarter' => [Deposit::share(2_500), 1_000, 250];
        yield 'a third, rounded as allocated' => [Deposit::share(3_334), 100, 33];
        yield 'a half of an odd total, the unit left to the deposit' => [Deposit::share(5_000), 101, 51];
        yield 'one basis point' => [Deposit::share(1), 10_000, 1];
        yield 'the whole' => [Deposit::share(Deposit::WHOLE), 1_001, 1_001];
        yield 'a fixed amount' => [Deposit::fixed(self::cad(300)), 1_000, 300];
        yield 'a fixed amount equal to the total' => [Deposit::fixed(self::cad(1_000)), 1_000, 1_000];
        yield 'a fixed amount above the total' => [Deposit::fixed(self::cad(1_500)), 1_000, 1_000];
        yield 'a fixed zero' => [Deposit::fixed(self::cad(0)), 1_000, 0];
        yield 'a share of nothing' => [Deposit::share(5_000), 0, 0];
        yield 'a share of a credit' => [Deposit::share(5_000), -1_000, 0];
    }

    #[DataProvider('deposits')]
    public function testTheDepositOfATotal(Deposit $deposit, int $total, int $expected): void
    {
        $amount = $deposit->of(self::cad($total));

        self::assertSame($expected, $amount->minorUnits);
        self::assertSame('CAD', $amount->currency->code);
    }

    public function testTheShareAndTheFixedAmountAreKept(): void
    {
        self::assertSame([2_500, null], [Deposit::share(2_500)->basisPoints, Deposit::share(2_500)->fixed]);
        self::assertSame([0, null], [Deposit::none()->basisPoints, Deposit::none()->fixed]);
        self::assertNull(Deposit::fixed(self::cad(1))->basisPoints);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function shares(): iterable
    {
        yield 'negative' => [-1];
        yield 'beyond the whole' => [Deposit::WHOLE + 1];
    }

    #[DataProvider('shares')]
    public function testAShareIsWithinTheWhole(int $share): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A deposit is 0 to 10000 basis points of the total, not %d.', $share));

        Deposit::share($share);
    }

    public function testAFixedDepositIsNotNegative(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains('A deposit is not a negative amount.');

        Deposit::fixed(self::cad(-1));
    }

    public function testAFixedDepositIsInTheCurrencyOfTheTotal(): void
    {
        $this->expectException(CurrencyMismatch::class);

        Deposit::fixed(self::cad(100))->of(Money::of(1_000, Currency::of('EUR')));
    }

    private static function cad(int $minorUnits): Money
    {
        return Money::of($minorUnits, Currency::of('CAD'));
    }
}
