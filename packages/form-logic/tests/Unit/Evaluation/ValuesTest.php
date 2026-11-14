<?php

declare(strict_types=1);

namespace Phil\FormLogic\Tests\Unit\Evaluation;

use Phil\FormLogic\Evaluation\Values;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Values::class)]
final class ValuesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: float, 1: string}>
     */
    public static function numbers(): iterable
    {
        yield 'NaN' => [\NAN, 'NaN'];
        yield 'infinity' => [\INF, 'Infinity'];
        yield 'minus infinity' => [-\INF, '-Infinity'];
        yield 'zero' => [0.0, '0'];
        yield 'minus zero' => [-0.0, '0'];
        yield 'an integer' => [42.0, '42'];
        yield 'a negative decimal' => [-2.5, '-2.5'];
        yield 'a third' => [1 / 3, '0.3333333333333333'];
        yield 'a sum' => [0.1 + 0.2, '0.30000000000000004'];
        yield '10^20' => [1e20, '100000000000000000000'];
        yield 'under 10^21' => [999_999_999_999_999_900_000.0, '999999999999999900000'];
        yield '10^21' => [1e21, '1e+21'];
        yield '1.5 x 10^21' => [1.5e21, '1.5e+21'];
        yield 'the largest' => [\PHP_FLOAT_MAX, '1.7976931348623157e+308'];
        yield '10^-6' => [0.000001, '0.000001'];
        yield '1.5 x 10^-6' => [0.0000015, '0.0000015'];
        yield '10^-7' => [1e-7, '1e-7'];
        yield '1.25 x 10^-7' => [1.25e-7, '1.25e-7'];
        yield 'the smallest' => [5e-324, '5e-324'];
        yield 'digits around the point' => [123.456, '123.456'];
        yield 'seventeen digits' => [0.1 + 0.7, '0.7999999999999999'];
        yield 'beyond 2^53' => [9_007_199_254_740_993.0, '9007199254740992'];
    }

    #[DataProvider('numbers')]
    public function testANumberIsWrittenAsJavaScriptWritesIt(float $number, string $text): void
    {
        self::assertSame($text, Values::numberToText($number));
    }

    public function testDataIsReadAsValues(): void
    {
        self::assertSame(3, Values::fromData(3.0));
        self::assertSame(0, Values::fromData(-0.0));
        self::assertSame(2.5, Values::fromData(2.5));
        self::assertSame(7, Values::fromData(7));
        self::assertSame(9_007_199_254_740_992.0, Values::fromData(9_007_199_254_740_993));
        self::assertNull(Values::fromData(\NAN));
        self::assertNull(Values::fromData(\INF));
        self::assertSame('a', Values::fromData('a'));
        self::assertFalse(Values::fromData(false));
        self::assertNull(Values::fromData(null));
        self::assertNull(Values::fromData(new stdClass()));
        self::assertNull(Values::fromData(['k' => 1]));
        self::assertSame([1, null, ['a']], Values::fromData([1.0, ['k' => 1], ['a']]));
        self::assertSame([], Values::fromData([]));
    }

    public function testDataListsAreReadSixteenDeep(): void
    {
        $list = 'leaf';

        for ($depth = 0; $depth < 17; ++$depth) {
            $list = [$list];
        }

        $read = Values::fromData($list);

        for ($depth = 0; $depth < 15; ++$depth) {
            self::assertIsArray($read);
            $read = $read[0];
        }

        self::assertSame([null], $read);
    }

    public function testTruth(): void
    {
        foreach ([null, false, 0, 0.0, -0.0, '', []] as $false) {
            self::assertFalse(Values::truthy($false), var_export($false, true));
        }

        foreach ([true, 1, -1, 0.5, '0', 'a', [0], [[]]] as $true) {
            self::assertTrue(Values::truthy($true), var_export($true, true));
        }
    }

    public function testNumbersAsLogicKeepsThem(): void
    {
        self::assertNull(Values::number(\NAN));
        self::assertNull(Values::number(-\INF));
        self::assertSame(0, Values::number(-0.0));
        self::assertSame(2.5, Values::number(2.5));
        self::assertSame(-7, Values::number(-7.0));
        self::assertSame(9_007_199_254_740_991, Values::number(9_007_199_254_740_991.0));
        self::assertSame(9_007_199_254_740_992.0, Values::number(9_007_199_254_740_992.0));
        self::assertSame(-9_007_199_254_740_992.0, Values::number(-9_007_199_254_740_992.0));
    }

    public function testValuesAsNumbers(): void
    {
        self::assertSame(12.0, Values::toNumber("\f\r 12\t"));
        self::assertSame(0.0, Values::toNumber(null));
        self::assertSame(0.0, Values::toNumber(false));
        self::assertSame(1.0, Values::toNumber(true));
        self::assertSame(3.0, Values::toNumber(3));
        self::assertSame(2.5, Values::toNumber(' 2.5 '));
        self::assertSame(0.0, Values::toNumber(''));
        self::assertSame(0.0, Values::toNumber("\t\n\x0B\f\r "));
        self::assertSame(-0.5, Values::toNumber('-.5'));
        self::assertSame(1000.0, Values::toNumber('1e3'));
        self::assertNan(Values::toNumber('1 2'));
        self::assertNan(Values::toNumber('e3'));
        self::assertNan(Values::toNumber('.'));
        self::assertNan(Values::toNumber('1e'));
        self::assertNan(Values::toNumber('x1'));
        self::assertNan(Values::toNumber('1x'));
        self::assertSame(4.0, Values::toNumber([4]));
        self::assertSame(0.0, Values::toNumber([]));
        self::assertNan(Values::toNumber([1, 2]));
    }

    public function testValuesAsText(): void
    {
        self::assertSame('null', Values::toText(null));
        self::assertSame('true', Values::toText(true));
        self::assertSame('false', Values::toText(false));
        self::assertSame('1.5', Values::toText(1.5));
        self::assertSame('7', Values::toText(7));
        self::assertSame('a', Values::toText('a'));
        self::assertSame('1,,a,true,2,3,', Values::toText([1, null, 'a', true, [2, [3, null]]]));
        self::assertSame('', Values::toText([]));
    }

    public function testLooseEquality(): void
    {
        self::assertTrue(Values::looselyEqual(null, null));
        self::assertFalse(Values::looselyEqual(null, 0));
        self::assertFalse(Values::looselyEqual('', null));
        self::assertTrue(Values::looselyEqual(true, true));
        self::assertFalse(Values::looselyEqual(true, false));
        self::assertTrue(Values::looselyEqual(true, '1'));
        self::assertTrue(Values::looselyEqual('1', true));
        self::assertTrue(Values::looselyEqual(false, []));
        self::assertTrue(Values::looselyEqual([], false));
        self::assertTrue(Values::looselyEqual(['a'], 'a'));
        self::assertTrue(Values::looselyEqual('a', ['a']));
        self::assertFalse(Values::looselyEqual(['a'], ['a']));
        self::assertTrue(Values::looselyEqual('a', 'a'));
        self::assertFalse(Values::looselyEqual('1', '1.0'));
        self::assertTrue(Values::looselyEqual(1, '1.0'));
        self::assertTrue(Values::looselyEqual(1, 1.0));
        self::assertFalse(Values::looselyEqual('x', 'y'));
    }

    public function testStrictEquality(): void
    {
        self::assertTrue(Values::strictlyEqual(1, 1.0));
        self::assertTrue(Values::strictlyEqual(1.5, 1.5));
        self::assertFalse(Values::strictlyEqual(1, '1'));
        self::assertFalse(Values::strictlyEqual('1', 1));
        self::assertTrue(Values::strictlyEqual('a', 'a'));
        self::assertTrue(Values::strictlyEqual(null, null));
        self::assertFalse(Values::strictlyEqual([], []));
        self::assertFalse(Values::strictlyEqual(false, 0));
    }

    public function testOrder(): void
    {
        self::assertTrue(Values::less('a', 'b'));
        self::assertFalse(Values::less('b', 'a'));
        self::assertFalse(Values::less('a', 'a'));
        self::assertTrue(Values::less('a', 'a', true));
        self::assertFalse(Values::less('b', 'a', true));
        self::assertTrue(Values::less(1, 2));
        self::assertFalse(Values::less(2, 2));
        self::assertTrue(Values::less(2, 2, true));
        self::assertFalse(Values::less(3, 2, true));
        self::assertTrue(Values::less(['a'], 'b'));
        self::assertTrue(Values::less('a', ['b']));
        self::assertTrue(Values::less('é', "\u{10000}"));
        self::assertFalse(Values::less("\u{FFFF}", "\u{10000}"));
        self::assertTrue(Values::less("\u{10000}", "\u{FFFF}"));
        self::assertTrue(Values::less("a\u{10000}", "a\u{FFFF}", true));
    }

    public function testMembership(): void
    {
        self::assertTrue(Values::contains('b', 'abc'));
        self::assertFalse(Values::contains('d', 'abc'));
        self::assertFalse(Values::contains('', ''));
        self::assertTrue(Values::contains(2, [1, 2.0]));
        self::assertFalse(Values::contains('2', [1, 2]));
        self::assertFalse(Values::contains('a', null));
        self::assertFalse(Values::contains('1', 1));
    }
}
