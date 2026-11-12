<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Colour;

use Phil\AccessibilityCheck\Colour\Colour;
use Phil\AccessibilityCheck\Colour\ColourParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColourParser::class)]
final class ColourParserTest extends TestCase
{
    /**
     * @return iterable<array{string, array{float, float, float, float}}>
     */
    public static function colours(): iterable
    {
        yield ['#fff', [255, 255, 255, 1]];
        yield ['#0f08', [0, 255, 0, 136 / 255]];
        yield ['#1a2b3c', [26, 43, 60, 1]];
        yield ['#1A2B3C80', [26, 43, 60, 128 / 255]];
        yield ['  Red !important ', [255, 0, 0, 1]];
        yield ['rebeccapurple', [102, 51, 153, 1]];
        yield ['aliceblue', [240, 248, 255, 1]];
        yield ['transparent', [0, 0, 0, 0]];
        yield ['rgb(1, 2, 3)', [1, 2, 3, 1]];
        yield ['rgba(1,2,3,0.5)', [1, 2, 3, 0.5]];
        yield ['rgb(1 2 3 / 25%)', [1, 2, 3, 0.25]];
        yield ['rgb( 100% 50% 0% )', [255, 127.5, 0, 1]];
        yield ['rgb(300 -5 none)', [255, 0, 0, 1]];
        yield ['rgba(0, 0, 0, 2)', [0, 0, 0, 1]];
        yield ['rgba(0, 0, 0, -1)', [0, 0, 0, 0]];
        yield ['hsl(0, 100%, 50%)', [255, 0, 0, 1]];
        yield ['hsl(120 100% 25%)', [0, 127.5, 0, 1]];
        yield ['hsl(240deg 100% 50%)', [0, 0, 255, 1]];
        yield ['hsl(-120deg 100% 50%)', [0, 0, 255, 1]];
        yield ['hsl(480 100% 50%)', [0, 255, 0, 1]];
        yield ['hsl(0.5turn 100% 50%)', [0, 255, 255, 1]];
        yield ['hsl(200grad 100% 50%)', [0, 255, 255, 1]];
        yield ['hsl(3.14159265359rad 100% 50%)', [0, 255, 255, 1]];
        yield ['hsla(0, 200%, 150%, 0.5)', [255, 255, 255, 0.5]];
        yield ['hsl(0 -50% -10%)', [0, 0, 0, 1]];
        yield ['hsl(0 0% 50%)', [127.5, 127.5, 127.5, 1]];
        yield ['hsl(60 100 50)', [255, 255, 0, 1]];
        yield ['hsl(none 100% 50%)', [255, 0, 0, 1]];
        yield ['hsl(0 50% 50%)', [191.25, 63.75, 63.75, 1]];
        yield ['hsl(15 100% 50%)', [255, 63.75, 0, 1]];
        yield ['hsl(30 100% 50%)', [255, 127.5, 0, 1]];
        yield ['oklch(1 0 0)', [255, 255, 255, 1]];
        yield ['oklch(0% 0 0)', [0, 0, 0, 1]];
        yield ['oklch(none none none / 50%)', [0, 0, 0, 0.5]];
        yield ['oklch(120% -1 0)', [255, 255, 255, 1]];
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function oklch(): iterable
    {
        yield ['oklch(62.8% 0.2577 29.23)', '#ff0000'];
        yield ['oklch(86.6% 0.2948 142.5)', '#00ff00'];
        yield ['oklch(45.2% 0.3132 264.05)', '#0000ff'];
        yield ['oklch(0.5 0.1 180 / 50%)', '#007565'];
        yield ['oklch(70% 50% 0.5turn)', '#00c4a3'];
        yield ['oklch(0.7 0.1 200grad)', '#4bb3a1'];
        yield ['OKLCH(62.8% 0.2577 29.23) !important', '#ff0000'];
        yield ['oklch(1 0.4 0)', '#ff20f4'];
    }

    #[DataProvider('oklch')]
    public function testAnOklchColourIsReadInSrgb(string $value, string $hex): void
    {
        self::assertSame($hex, ColourParser::parse($value)?->toHex(), $value);
    }

    /**
     * @param array{float, float, float, float} $expected
     */
    #[DataProvider('colours')]
    public function testAColourIsRead(string $value, array $expected): void
    {
        $colour = ColourParser::parse($value);

        self::assertInstanceOf(Colour::class, $colour);
        self::assertEqualsWithDelta($expected, [$colour->red, $colour->green, $colour->blue, $colour->alpha], 1e-6, $value);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function uncomputable(): iterable
    {
        yield ['var(--accent)'];
        yield ['color-mix(in oklch, red, blue)'];
        yield ['oklch(0.5 0.1)'];
        yield ['oklch(x 0.1 200)'];
        yield ['oklch(0.5 x 200)'];
        yield ['oklch(0.5 0.1 x)'];
        yield ['oklch(0.5 0.1 200 / x)'];
        yield ['#ff'];
        yield ['#fffff'];
        yield ['#ggg'];
        yield ['rgb(1, 2)'];
        yield ['rgb(1, 2, 3, 4, 5)'];
        yield ['rgb(a, 2, 3)'];
        yield ['rgb(1, 2, 3, x)'];
        yield ['rgb(1%x, 2, 3)'];
        yield ['hsl(1, 2%, 3%, x)'];
        yield ['hsl(x, 2%, 3%)'];
        yield ['hsl(1, x, 3%)'];
        yield ['hsl(1, 2%, x)'];
        yield ['hsl(1radgrad 2% 3%)'];
        yield ['hsl(1 2% 3%'];
        yield ['notacolour'];
        yield ['inherit'];
        yield ['xrgb(1, 2, 3)'];
        yield ['rgb(1, 2, 3)x'];
    }

    #[DataProvider('uncomputable')]
    public function testAColourThatCannotBeComputedIsNull(string $value): void
    {
        self::assertNull(ColourParser::parse($value));
    }

    public function testTheInheritingValuesAreKnown(): void
    {
        foreach (['inherit', 'CurrentColor', ' unset ', 'initial', 'revert', 'revert-layer', 'inherit !important'] as $value) {
            self::assertTrue(ColourParser::isInherited($value), $value);
        }

        self::assertFalse(ColourParser::isInherited('red'));
    }

    public function testTheValuesOfTheStyleSheetsAreKnown(): void
    {
        foreach (['var(--a)', ' VAR(--a) !important', 'color-mix(in srgb, red, blue)', 'rgb(var(--r) 0 0)', 'var(--a,red)'] as $value) {
            self::assertTrue(ColourParser::isUnresolved($value), $value);
        }

        foreach (['red', 'oklch(0.5 0.1 200)', 'myvar(--a)', 'my-var(--a)', 'xcolor-mix(a)'] as $value) {
            self::assertFalse(ColourParser::isUnresolved($value), $value);
        }
    }
}
