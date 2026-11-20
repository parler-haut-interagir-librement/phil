<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Tests\Unit\Domain;

use Phil\CommercePosBundle\Domain\Model\Register;
use Phil\CommercePosBundle\Domain\ValueObject\CashRounding;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\Money\Currency;
use Phil\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Register::class)]
#[CoversClass(CashRounding::class)]
final class RegisterTest extends TestCase
{
    public function testARegisterHasItsDrawer(): void
    {
        $register = Register::of('front', 'Front counter', Currency::of('CAD'), 5, 'store-1', 'shop');

        self::assertSame(['front', 'Front counter', 'CAD', 5, 'store-1', 'shop', true], [
            $register->code, $register->name(), $register->currency()->code, $register->rounding()->increment, $register->location(), $register->channel(), $register->isActive(),
        ]);
    }

    public function testByDefaultEveryCoinIsInUse(): void
    {
        $register = Register::of(str_repeat('a', 32), str_repeat('é', 255), Currency::of('USD'));

        self::assertSame([1, null, null], [$register->rounding()->increment, $register->location(), $register->channel()]);
    }

    public function testARegisterIsChanged(): void
    {
        $register = Register::of('front', 'Front counter', Currency::of('CAD'));

        $register->rename('Back counter');
        $register->roundCashTo(10);
        $register->place('store-2', null);
        $register->retire();

        self::assertSame(['Back counter', 10, 'store-2', null, false], [$register->name(), $register->rounding()->increment, $register->location(), $register->channel(), $register->isActive()]);

        $register->reinstate();
        self::assertTrue($register->isActive());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 33)];
        yield 'uppercase' => ['Front'];
        yield 'a sign first' => ['-front'];
        yield 'a trailing newline' => ["front\n"];
    }

    #[DataProvider('badCodes')]
    public function testACodeIsShortAndLowercase(string $code): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a register code: up to 32 lowercase letters, digits, "_" or "-".', $code));

        Register::of($code, 'Front', Currency::of('CAD'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'blank' => [' '];
        yield 'too long' => [str_repeat('é', 256)];
    }

    #[DataProvider('badNames')]
    public function testARegisterHasAName(string $name): void
    {
        try {
            Register::of('front', $name, Currency::of('CAD'));
            self::fail('A bad name was accepted.');
        } catch (InvalidValue $invalidValue) {
            self::assertSame('The register front has a name of 1 to 255 characters.', $invalidValue->getMessage());
        }

        $this->expectException(InvalidValue::class);

        Register::of('front', 'Front', Currency::of('CAD'))->rename($name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badReferences(): iterable
    {
        yield 'empty' => [''];
        yield 'a space' => ['store 1'];
        yield 'too long' => [str_repeat('s', 65)];
        yield 'a trailing newline' => ["store\n"];
    }

    #[DataProvider('badReferences')]
    public function testALocationAndAChannelAreReferences(string $reference): void
    {
        $message = \sprintf('"%s" is not a location or a channel: 1 to 64 visible ASCII characters.', $reference);

        foreach ([static fn (): Register => Register::of('front', 'Front', Currency::of('CAD'), 1, $reference), static fn (): Register => Register::of('front', 'Front', Currency::of('CAD'), 1, null, $reference), static fn () => Register::of('front', 'Front', Currency::of('CAD'))->place(null, $reference)] as $make) {
            try {
                $make();
                self::fail('A bad reference was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        self::assertSame(str_repeat('s', 64), Register::of('front', 'Front', Currency::of('CAD'), 1, str_repeat('s', 64))->location());
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function roundings(): iterable
    {
        yield 'already round' => [5, 100, 100];
        yield 'one down' => [5, 101, 100];
        yield 'two down' => [5, 102, 100];
        yield 'three up' => [5, 103, 105];
        yield 'four up' => [5, 104, 105];
        yield 'six down' => [5, 106, 105];
        yield 'eight up' => [5, 108, 110];
        yield 'a refund' => [5, -103, -105];
        yield 'a small refund' => [5, -102, -100];
        yield 'every coin' => [1, 103, 103];
        yield 'tens, half up' => [10, 105, 110];
        yield 'tens, below half' => [10, 104, 100];
        yield 'zero' => [5, 0, 0];
    }

    #[DataProvider('roundings')]
    public function testCashRoundsToTheNearestIncrement(int $increment, int $amount, int $rounded): void
    {
        self::assertSame($rounded, CashRounding::of($increment)->round(Money::of($amount, Currency::of('CAD')))->minorUnits);
    }

    public function testTheIncrementIsBounded(): void
    {
        self::assertSame(100, CashRounding::of(100)->increment);

        foreach ([0, 101] as $increment) {
            try {
                CashRounding::of($increment);
                self::fail('A bad increment was accepted.');
            } catch (InvalidValue $invalid) {
                self::assertSame(\sprintf('A cash increment is 1 to 100 minor units; %d is refused.', $increment), $invalid->getMessage());
            }
        }

        $this->expectException(InvalidValue::class);

        Register::of('front', 'Front', Currency::of('CAD'))->roundCashTo(0);
    }
}
