<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Tests\Unit\Domain;

use Phil\Money\Currency;
use Phil\Money\Money;
use Phil\ReceiptEmail\Domain\Exception\InvalidReceipt;
use Phil\ReceiptEmail\Domain\Guard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Guard::class)]
#[CoversClass(InvalidReceipt::class)]
final class GuardTest extends TestCase
{
    public function testATextIsKeptAsGiven(): void
    {
        self::assertSame(' Montréal ', Guard::text(' Montréal ', 'A city'));
        self::assertSame(str_repeat('é', 500), Guard::text(str_repeat('é', 500), 'A city'));
        self::assertSame('abc', Guard::text('abc', 'A code', 3));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function refusedTexts(): iterable
    {
        yield 'blank' => ['   ', 500];
        yield 'empty' => ['', 500];
        yield 'invalid UTF-8' => ["\xC3\x28", 500];
        yield 'line break' => ["a\nb", 500];
        yield 'tab' => ["a\tb", 500];
        yield 'delete' => ["a\x7Fb", 500];
        yield 'null byte' => ["a\x00b", 500];
        yield 'too long' => [str_repeat('é', 501), 500];
        yield 'longer than its own limit' => ['abcd', 3];
    }

    #[DataProvider('refusedTexts')]
    public function testATextIsRefused(string $text, int $max): void
    {
        $this->expectException(InvalidReceipt::class);
        $this->expectExceptionMessageIs(\sprintf('A city is a text of 1 to %d characters, without line breaks or control characters.', $max));

        Guard::text($text, 'A city', $max);
    }

    public function testLinesAreAListOfTexts(): void
    {
        self::assertSame([], Guard::lines([], 'An address'));
        self::assertSame(array_fill(0, 10, 'a'), Guard::lines(array_fill(0, 10, 'a'), 'An address'));
        self::assertSame(['a', 'b'], Guard::lines(['a', 'b'], 'An address', 2));
    }

    public function testTooManyLinesAreRefused(): void
    {
        $this->expectExceptionMessageIs('An address holds a list of at most 10 lines.');

        Guard::lines(array_fill(0, 11, 'a'), 'An address');
    }

    public function testLinesBeyondTheirOwnLimitAreRefused(): void
    {
        $this->expectExceptionMessageIs('Options holds a list of at most 2 lines.');

        Guard::lines(['a', 'b', 'c'], 'Options', 2);
    }

    public function testEachLineIsAText(): void
    {
        $this->expectExceptionMessageIs('An address is a text of 1 to 500 characters, without line breaks or control characters.');

        Guard::lines(['a', ' '], 'An address');
    }

    public function testAnIdentifierIsVisibleAscii(): void
    {
        self::assertSame('R-000042', Guard::identifier('R-000042', 'A number'));
        self::assertSame('!~', Guard::identifier('!~', 'A number'));
        self::assertSame(str_repeat('a', 64), Guard::identifier(str_repeat('a', 64), 'A number'));
        self::assertSame(str_repeat('a', 32), Guard::identifier(str_repeat('a', 32), 'A number', 32));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function refusedIdentifiers(): iterable
    {
        yield 'empty' => ['', 64];
        yield 'space' => ['R 1', 64];
        yield 'accent' => ['é', 64];
        yield 'line break at the end' => ["R-1\n", 64];
        yield 'too long' => [str_repeat('a', 65), 64];
        yield 'longer than its own limit' => [str_repeat('a', 33), 32];
    }

    #[DataProvider('refusedIdentifiers')]
    public function testAnIdentifierIsRefused(string $identifier, int $max): void
    {
        $this->expectException(InvalidReceipt::class);
        $this->expectExceptionMessageIs(\sprintf('A number is 1 to %d visible ASCII characters.', $max));

        Guard::identifier($identifier, 'A number', $max);
    }

    public function testAnAmountIsInTheReceiptsCurrency(): void
    {
        $amount = Money::of(100, Currency::of('CAD'));

        self::assertSame($amount, Guard::currency($amount, Currency::of('CAD'), 'A line'));

        $this->expectExceptionMessageIs('A line is in USD, and the receipt in CAD.');

        Guard::currency(Money::of(100, Currency::of('USD')), Currency::of('CAD'), 'A line');
    }
}
