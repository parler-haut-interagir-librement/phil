<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Token;

use Phil\UxCss\Token\CssValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CssValue::class)]
final class CssValueTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>|null}>
     */
    public static function families(): iterable
    {
        yield 'quoted and not' => ['"Segoe UI", serif', ['Segoe UI', 'serif']];
        yield 'quotes inside a name' => ['x"y"', ['x"y"']];
        yield 'text after the quotes' => ['"a"b', ['"a"b']];
        yield 'a newline inside the quotes' => ["\"a\nb\"", ["a\nb"]];
        yield 'an empty name between commas' => ['a,,b', null];
        yield 'an empty quoted name' => ['serif, ""', null];
    }

    /**
     * @param list<string>|null $families
     */
    #[DataProvider('families')]
    public function testItReadsFontFamilies(string $css, ?array $families): void
    {
        self::assertSame(null === $families ? null : ['fontFamily', $families], CssValue::fontFamily($css));
    }

    /**
     * @return iterable<string, array{string, list<float|int>|null}>
     */
    public static function beziers(): iterable
    {
        yield 'within bounds' => ['cubic-bezier(0.22, 1, 0.36, 1)', [0.22, 1, 0.36, 1]];
        yield 'the first point at 1' => ['cubic-bezier(1, 0, 1, 1)', [1, 0, 1, 1]];
        yield 'the second point at 0' => ['cubic-bezier(0, 0, 0, 1)', [0, 0, 0, 1]];
        yield 'going beyond on the curve' => ['cubic-bezier(0.5, 2, 0.5, -1)', [0.5, 2, 0.5, -1]];
        yield 'the second point beyond 1' => ['cubic-bezier(0, 0, 1.5, 1)', null];
        yield 'the second point below 0' => ['cubic-bezier(0, 0, -0.1, 1)', null];
        yield 'the first point beyond 1' => ['cubic-bezier(1.5, 0, 0, 1)', null];
        yield 'the first point below 0' => ['cubic-bezier(-0.1, 0, 0, 1)', null];
    }

    /**
     * @param list<float|int>|null $points
     */
    #[DataProvider('beziers')]
    public function testItReadsCubicBeziers(string $css, ?array $points): void
    {
        self::assertSame(null === $points ? null : ['cubicBezier', $points], CssValue::cubicBezier($css));
    }

    public function testAListCutOnSpacesDropsTheEmptyPieces(): void
    {
        self::assertSame(['a', 'b(c  d)', 'e'], CssValue::split("  a \t b(c  d)\n\ne  ", ' '));
        self::assertSame(['a', '', 'b'], CssValue::split('a,,b', ','));
    }

    /**
     * @return iterable<string, array{float, float|int}>
     */
    public static function numbers(): iterable
    {
        yield 'whole' => [2.0, 2];
        yield 'negative and whole' => [-3.0, -3];
        yield 'a fraction' => [0.5, 0.5];
        yield 'a negative fraction' => [-0.5, -0.5];
        yield 'rounded to whole' => [1.000000001, 1];
        yield 'the greatest whole number written as an integer' => [999_999_999_999_999.0, 999_999_999_999_999];
        yield 'too great for an integer' => [1e15, 1e15];
    }

    #[DataProvider('numbers')]
    public function testNumbersAreRoundedAndWholeOnesAreIntegers(float $value, float|int $number): void
    {
        self::assertSame($number, CssValue::number($value));
    }
}
