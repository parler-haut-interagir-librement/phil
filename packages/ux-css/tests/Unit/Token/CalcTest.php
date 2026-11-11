<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Token;

use Closure;
use Phil\UxCss\Token\Calc;
use Phil\UxCss\Token\Quantity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Calc::class)]
#[CoversClass(Quantity::class)]
final class CalcTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private const array PROPERTIES = [
        '--scale' => '2',
        '--unit' => 'calc(1rem * var(--scale))',
        '--ratio' => '1.25',
        '--loop' => 'var(--loop)',
        '--word' => 'auto',
        '--zero' => '0',
    ];

    /**
     * @return iterable<string, array{string, float, string}>
     */
    public static function values(): iterable
    {
        yield 'a number' => ['1.25', 1.25, ''];
        yield 'a negative number' => ['-3', -3.0, ''];
        yield 'a number without integer part' => ['.5', 0.5, ''];
        yield 'an exponent' => ['1e2', 100.0, ''];
        yield 'a length' => ['2px', 2.0, 'px'];
        yield 'a unit in capitals' => ['2PX', 2.0, 'px'];
        yield 'a percentage' => ['12%', 12.0, '%'];
        yield 'a duration' => ['150ms', 150.0, 'ms'];
        yield 'spaces around' => ['  3rem ', 3.0, 'rem'];
        yield 'a product' => ['calc(0.25rem * 4)', 1.0, 'rem'];
        yield 'a product, the number first' => ['calc(4 * 0.25rem)', 1.0, 'rem'];
        yield 'a division' => ['calc(1rem / 4)', 0.25, 'rem'];
        yield 'a division of lengths' => ['calc(3rem / 2rem)', 1.5, ''];
        yield 'a sum' => ['calc(1rem + 0.5rem)', 1.5, 'rem'];
        yield 'a difference' => ['calc(1rem - 0.25rem)', 0.75, 'rem'];
        yield 'the product first' => ['calc(1 + 2 * 3)', 7.0, ''];
        yield 'from left to right' => ['calc(8 / 2 / 2)', 2.0, ''];
        yield 'a difference from left to right' => ['calc(8 - 2 - 2)', 4.0, ''];
        yield 'parentheses' => ['calc((1 + 2) * 3)', 9.0, ''];
        yield 'a nested calc' => ['calc(calc(1rem * 2) * 2)', 4.0, 'rem'];
        yield 'a negated group' => ['calc(-(1rem))', -1.0, 'rem'];
        yield 'a property' => ['var(--scale)', 2.0, ''];
        yield 'a property with a fallback' => ['var(--scale, 3)', 2.0, ''];
        yield 'properties through properties' => ['calc(var(--unit) / var(--ratio))', 1.6, 'rem'];
        yield 'zero' => ['var(--zero)', 0.0, ''];
    }

    #[DataProvider('values')]
    public function testItComputesWhatAStylesheetWould(string $expression, float $value, string $unit): void
    {
        $quantity = Calc::evaluate($expression, $this->lookup(...));

        self::assertNotNull($quantity);
        self::assertEqualsWithDelta($value, $quantity->value, 1e-9);
        self::assertSame($unit, $quantity->unit);
        self::assertSame('' === $unit, $quantity->isNumber());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unknowable(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'a word' => ['auto'];
        yield 'two units summed' => ['calc(1rem + 1px)'];
        yield 'a number and a length summed' => ['calc(1rem + 1)'];
        yield 'two lengths multiplied' => ['calc(1rem * 1rem)'];
        yield 'a division by zero' => ['calc(1rem / 0)'];
        yield 'a division by a zero property' => ['calc(1rem / var(--zero))'];
        yield 'a division of two units' => ['calc(1rem / 1px)'];
        yield 'an unclosed parenthesis' => ['calc(1rem * 2'];
        yield 'a parenthesis closed twice' => ['calc(1rem * 2))'];
        yield 'two numbers in a row' => ['1 2'];
        yield 'an operator alone' => ['*'];
        yield 'an operator at the end' => ['calc(1 *)'];
        yield 'an operator at the end of a sum' => ['calc(1 +)'];
        yield 'a closing parenthesis first' => [')'];
        yield 'an unknown property' => ['var(--nowhere)'];
        yield 'a property of a word' => ['var(--word)'];
        yield 'a property in a cycle' => ['var(--loop)'];
        yield 'another function' => ['min(1rem, 2rem)'];
        yield 'too long' => [str_repeat(' ', 1021).'1rem'];
        yield 'too many pieces' => [implode(' + ', array_fill(0, 130, '1'))];
        yield 'one piece too many' => [implode(' + ', array_fill(0, 129, '1'))];
        yield 'a word after a value' => ['1rem auto'];
        yield 'an unknown property after a value' => ['1rem var(--nowhere)'];
        yield 'an operator opening a group' => ['* 2)'];
    }

    #[DataProvider('unknowable')]
    public function testWhatItCannotComputeHasNoValue(string $expression): void
    {
        self::assertNull(Calc::evaluate($expression, $this->lookup(...)));
    }

    public function testTheLongestTextAndTheMostPiecesAreRead(): void
    {
        self::assertEqualsWithDelta(1.0, Calc::evaluate(str_repeat(' ', 1020).'1rem', $this->lookup(...))?->value, 1e-9);
        self::assertEqualsWithDelta(128.0, Calc::evaluate(implode(' + ', array_fill(0, 128, '1')), $this->lookup(...))?->value, 1e-9);
        self::assertEqualsWithDelta(-128.0, Calc::evaluate('- '.implode(' - ', array_fill(0, 128, '1')), $this->lookup(...))?->value, 1e-9);
    }

    public function testAPropertyThatCannotBeComputedStopsTheReadingAtOnce(): void
    {
        $reads = [];
        $lookup = static function (string $name) use (&$reads): ?string {
            $reads[] = $name;

            return '--two' === $name ? '2' : null;
        };

        self::assertNull(Calc::evaluate('calc(var(--nowhere) + var(--two))', $lookup));
        self::assertSame(['--nowhere'], $reads);
    }

    public function testTheDeepestChainOfPropertiesIsFollowed(): void
    {
        $chain = static fn (int $length): Closure => static function (string $name) use ($length): string {
            $index = (int) substr($name, 3);

            return $index >= $length ? '1px' : 'var(--p'.($index + 1).')';
        };

        self::assertEqualsWithDelta(1.0, Calc::evaluate('var(--p0)', $chain(15))?->value, 1e-9);
        self::assertNull(Calc::evaluate('var(--p0)', $chain(16)));
    }

    public function testAPropertyNamedManyTimesIsComputedOnce(): void
    {
        $reads = [];
        // Each level names the next one four times: computed again each time, the last would be read 4^15 times.
        $lookup = static function (string $name) use (&$reads): string {
            $reads[$name] = ($reads[$name] ?? 0) + 1;
            $index = (int) substr($name, 3);
            $next = 'var(--p'.($index + 1).')';

            return $index >= 15 ? '1px' : \sprintf('calc(%1$s + %1$s + %1$s + %1$s)', $next);
        };

        self::assertEqualsWithDelta(4 ** 15, Calc::evaluate('var(--p0)', $lookup)?->value, 1e-3);
        self::assertSame(array_fill_keys(array_map(static fn (int $index): string => '--p'.$index, range(0, 15)), 1), $reads);
    }

    private function lookup(string $name): ?string
    {
        return self::PROPERTIES[$name] ?? null;
    }
}
