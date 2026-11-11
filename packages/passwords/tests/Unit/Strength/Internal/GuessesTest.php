<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength\Internal;

use Phil\Passwords\Strength\Internal\Guesses;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Guesses::class)]
final class GuessesTest extends TestCase
{
    public function testBinomialCoefficients(): void
    {
        self::assertSame(1.0, Guesses::binomial(5, 0));
        self::assertSame(5.0, Guesses::binomial(5, 1));
        self::assertSame(10.0, Guesses::binomial(5, 2));
        self::assertSame(1.0, Guesses::binomial(5, 5));
        self::assertSame(1.0, Guesses::binomial(0, 0));
        self::assertSame(1.0, Guesses::binomial(4, 4));
    }

    public function testVariations(): void
    {
        self::assertSame(2.0, Guesses::variations(3, 0));
        self::assertSame(2.0, Guesses::variations(1, 1));
        // C(5,1) + C(5,2) = 5 + 10
        self::assertSame(15.0, Guesses::variations(2, 3));
        self::assertSame(15.0, Guesses::variations(3, 2));
        self::assertSame(4.0, Guesses::variations(1, 3));
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function uppercase(): iterable
    {
        yield 'lowercase' => ['password', 1.0];
        yield 'no letter' => ['1234', 1.0];
        yield 'capitalised' => ['Password', 2.0];
        yield 'capitalised with digits' => ['Password12', 2.0];
        yield 'last letter' => ['passworD', 2.0];
        yield 'all uppercase' => ['PASSWORD', 2.0];
        yield 'all uppercase with digits' => ['PASS1', 2.0];
        yield 'two uppercase' => ['PaSsword', 36.0];
        yield 'mixed' => ['pAssword', 8.0];
        yield 'single uppercase letter' => ['A', 2.0];
        yield 'accented' => ['Été', 2.0];
        yield 'last letter accented in uppercase' => ['passwordÉ', 2.0];
    }

    #[DataProvider('uppercase')]
    public function testUppercaseVariations(string $token, float $expected): void
    {
        self::assertSame($expected, Guesses::uppercaseVariations($token));
    }

    public function testL33tVariations(): void
    {
        self::assertSame(1.0, Guesses::l33tVariations('password', []));
        // `@` replaces every `a`: tried in both forms.
        self::assertSame(2.0, Guesses::l33tVariations('p@ssword', ['@' => 'a']));
        // One of two `a`: C(2,1).
        self::assertSame(2.0, Guesses::l33tVariations('p@ssa', ['@' => 'a']));
        // One `@` and two `a`: C(3,1).
        self::assertSame(3.0, Guesses::l33tVariations('@aa', ['@' => 'a']));
        // `İ` lowercases to `i` and a combining dot: two `i` left as they are, C(3,1).
        self::assertSame(3.0, Guesses::l33tVariations('1İİ', ['1' => 'i']));
        // Digit keys, as PHP stores them, and case ignored.
        self::assertSame(4.0, Guesses::l33tVariations('P@SSW0RD', ['@' => 'a', 0 => 'o']));
    }
}
