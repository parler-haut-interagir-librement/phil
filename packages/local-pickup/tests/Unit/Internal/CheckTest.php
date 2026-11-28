<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Tests\Unit\Internal;

use Phil\LocalPickup\Exception\InvalidPickup;
use Phil\LocalPickup\Internal\Check;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Check::class)]
#[CoversClass(InvalidPickup::class)]
final class CheckTest extends TestCase
{
    public function testItAcceptsAndGivesBackValuesWithinBounds(): void
    {
        self::assertSame('A', Check::order('A'));
        self::assertSame('WEB-2026/0042#1.b_c', Check::order('WEB-2026/0042#1.b_c'));
        self::assertSame('0'.str_repeat('a', 63), Check::order('0'.str_repeat('a', 63)));
        self::assertSame('store-1.a_b', Check::code('point', 'store-1.a_b'));
        self::assertSame('9'.str_repeat('z', 63), Check::code('point', '9'.str_repeat('z', 63)));
        self::assertSame('user-42', Check::opaque('customer', 'user-42'));
        self::assertSame(str_repeat('x', 255), Check::opaque('customer', str_repeat('x', 255)));
        self::assertSame('Élise Tremblay', Check::text('name', 'Élise Tremblay'));
        self::assertSame(str_repeat('é', 250), Check::text('name', str_repeat('é', 250)));
        self::assertSame("12 Rue\nMontréal", Check::text('address', "12 Rue\nMontréal", multiline: true));
        self::assertSame(1, Check::range('n', 1, 1, 3));
        self::assertSame(3, Check::range('n', 3, 1, 3));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badOrders(): iterable
    {
        yield 'empty' => [''];
        yield 'leading dash' => ['-1'];
        yield 'colon' => ['A:1'];
        yield 'space' => ['A 1'];
        yield 'too long' => ['A'.str_repeat('a', 64)];
        yield 'trailing newline' => ["A1\n"];
    }

    #[DataProvider('badOrders')]
    public function testItRefusesAnOrderNumberOutOfShape(string $order): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The order number is up to 64 letters');

        Check::order($order);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'capital' => ['Store'];
        yield 'leading dot' => ['.a'];
        yield 'too long' => ['a'.str_repeat('a', 64)];
        yield 'trailing newline' => ["a\n"];
    }

    #[DataProvider('badCodes')]
    public function testItRefusesACodeOutOfShape(string $code): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The point is up to 64 lowercase letters, digits, "_", "." or "-".');

        Check::code('point', $code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badOpaque(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('x', 256)];
        yield 'space' => ['a b'];
        yield 'tab' => ["a\tb"];
        yield 'delete' => ["a\x7F"];
        yield 'control' => ["a\x01"];
    }

    #[DataProvider('badOpaque')]
    public function testItRefusesAnOpaqueIdentifierOutOfShape(string $value): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The staff is 1 to 255 bytes without whitespace nor control characters.');

        Check::opaque('staff', $value);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function badTexts(): iterable
    {
        yield 'empty' => ['', false];
        yield 'blank' => ['   ', false];
        yield 'too long' => [str_repeat('a', 501), false];
        yield 'invalid UTF-8' => ["caf\xE9", false];
        yield 'newline in a line' => ["a\nb", false];
        yield 'tab in an address' => ["a\tb", true];
        yield 'carriage return in an address' => ["a\rb", true];
        yield 'delete' => ["a\x7F", true];
        yield 'nul' => ["a\x00", true];
    }

    #[DataProvider('badTexts')]
    public function testItRefusesATextOutOfShape(string $value, bool $multiline): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The name is a text of 1 to 500 bytes of UTF-8 without control characters.');

        Check::text('name', $value, $multiline);
    }

    public function testItRefusesANumberBelowItsRange(): void
    {
        $this->expectException(InvalidPickup::class);
        $this->expectExceptionMessageIsOrContains('The n is between 1 and 3.');

        Check::range('n', 0, 1, 3);
    }

    public function testItRefusesANumberAboveItsRange(): void
    {
        $this->expectException(InvalidPickup::class);

        Check::range('n', 4, 1, 3);
    }
}
