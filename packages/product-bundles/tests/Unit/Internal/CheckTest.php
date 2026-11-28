<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Tests\Unit\Internal;

use Phil\Money\Currency;
use Phil\ProductBundles\Exception\InvalidBundle;
use Phil\ProductBundles\Internal\Check;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Check::class)]
#[CoversClass(InvalidBundle::class)]
final class CheckTest extends TestCase
{
    public function testTheValuesWithinBoundsAreGivenBack(): void
    {
        self::assertSame('A', Check::sku('SKU', 'A'));
        self::assertSame('seller 7 — Montréal', Check::opaque('seller', 'seller 7 — Montréal'));
        self::assertSame(str_repeat('x', 255), Check::opaque('seller', str_repeat('x', 255)));
        self::assertSame(5, Check::between('n', 5, 5, 5));
        self::assertSame('v', Check::string(['k' => 'v'], 'k'));
        self::assertSame(3, Check::int(['k' => 3], 'k'));
        self::assertNull(Check::nullableInt([], 'k'));
        self::assertSame(4, Check::nullableInt(['k' => 4], 'k'));
        self::assertSame([1, 2], Check::list(['k' => [1, 2]], 'k', 2));
        self::assertSame(['a' => 1], Check::row(['a' => 1], 'row'));
        self::assertSame('EUR', Check::currency('EUR')->code);
        self::assertSame(250, Check::money(['k' => 250], 'k', Currency::of('CAD'))->minorUnits);
        self::assertNull(Check::nullableMoney(['k' => null], 'k', Currency::of('CAD')));
        self::assertSame(-5, Check::nullableMoney(['k' => -5], 'k', Currency::of('CAD'))?->minorUnits);
    }

    /**
     * @param callable(): mixed $check
     */
    #[DataProvider('refusals')]
    public function testTheValuesOutOfBoundsAreRefused(callable $check, string $message): void
    {
        $this->expectExceptionObject(new InvalidBundle($message));

        $check();
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusals(): iterable
    {
        yield 'opaque empty' => [static fn (): string => Check::opaque('seller', ''), 'The seller is from 1 to 255 printable characters.'];
        yield 'opaque long' => [static fn (): string => Check::opaque('seller', str_repeat('x', 256)), 'The seller is from 1 to 255 printable characters.'];
        yield 'opaque control' => [static fn (): string => Check::opaque('seller', "a\x7F"), 'The seller is from 1 to 255 printable characters.'];
        yield 'opaque trailing newline' => [static fn (): string => Check::opaque('seller', "a\n"), 'The seller is from 1 to 255 printable characters.'];
        yield 'opaque invalid utf-8' => [static fn (): string => Check::opaque('seller', "\xC3"), 'The seller is from 1 to 255 printable characters.'];
        yield 'below' => [static fn (): int => Check::between('n', 4, 5, 6), 'The n is from 5 to 6, not 4.'];
        yield 'above' => [static fn (): int => Check::between('n', 7, 5, 6), 'The n is from 5 to 6, not 7.'];
        yield 'missing string' => [static fn (): string => Check::string([], 'k'), 'The stored field "k" is a string.'];
        yield 'missing int' => [static fn (): int => Check::int([], 'k'), 'The stored field "k" is an integer.'];
        yield 'nullable int' => [static fn (): ?int => Check::nullableInt(['k' => 1.5], 'k'), 'The stored field "k" is an integer.'];
        yield 'long list' => [static fn (): array => Check::list(['k' => [1, 2, 3]], 'k', 2), 'The stored field "k" is a list of at most 2 entries.'];
        yield 'map' => [static fn (): array => Check::list(['k' => [1 => 'a']], 'k', 2), 'The stored field "k" is a list of at most 2 entries.'];
        yield 'not a row' => [static fn (): array => Check::row('x', 'stored row'), 'The stored row is an array.'];
        yield 'currency' => [static fn (): Currency => Check::currency('ZZZ'), 'The currency is a code of ISO 4217.'];
        yield 'money' => [static fn (): mixed => Check::money(['k' => \PHP_INT_MIN], 'k', Currency::of('CAD')), 'The stored field "k" is an amount.'];
    }
}
