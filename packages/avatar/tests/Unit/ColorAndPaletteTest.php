<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit;

use Phil\Avatar\Color;
use Phil\Avatar\Exception\InvalidAvatar;
use Phil\Avatar\Palette;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Color::class)]
#[CoversClass(Palette::class)]
final class ColorAndPaletteTest extends TestCase
{
    public function testAColorIsReadAndWritten(): void
    {
        $color = Color::fromHex('#1E5AA8');

        self::assertSame([30, 90, 168], [$color->red, $color->green, $color->blue]);
        self::assertSame('#1e5aa8', $color->toHex());
        self::assertSame('#ffaa00', Color::fromHex('#fa0')->toHex());
        self::assertSame('#000000', Color::fromHex('#000')->toHex());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidColors(): iterable
    {
        yield 'no hash' => ['1e5aa8'];
        yield 'a name' => ['red'];
        yield 'four digits' => ['#1e5a'];
        yield 'not hexadecimal' => ['#1e5aag'];
        yield 'a new line after' => ["#1e5aa8\n"];
        yield 'markup' => ['#fff" onload="x'];
    }

    #[DataProvider('invalidColors')]
    public function testAColorNotWrittenInHexadecimalIsRefused(string $hex): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('A color is written #rrggbb or #rgb.'));

        Color::fromHex($hex);
    }

    public function testLuminanceAndContrastFollowWcag(): void
    {
        $white = Color::fromHex('#ffffff');
        $black = Color::fromHex('#000000');

        self::assertEqualsWithDelta(1.0, $white->luminance(), 1e-12);
        self::assertEqualsWithDelta(0.0, $black->luminance(), 1e-12);
        self::assertEqualsWithDelta(21.0, $white->contrastWith($black), 1e-9);
        self::assertEqualsWithDelta(21.0, $black->contrastWith($white), 1e-9);
        self::assertEqualsWithDelta(1.0, $white->contrastWith($white), 1e-12);
        // Pure red, green and blue weigh by their share of the luminance.
        self::assertEqualsWithDelta(0.2126, Color::fromHex('#ff0000')->luminance(), 1e-12);
        self::assertEqualsWithDelta(0.7152, Color::fromHex('#00ff00')->luminance(), 1e-12);
        self::assertEqualsWithDelta(0.0722, Color::fromHex('#0000ff')->luminance(), 1e-12);
        // #767676 on white is the classic 4.54:1; the dark end of the curve is linear.
        self::assertEqualsWithDelta(4.54, Color::fromHex('#767676')->contrastWith($white), 0.01);
        self::assertEqualsWithDelta(10 / 255 / 12.92, Color::fromHex('#0a0a0a')->luminance(), 1e-12);
        self::assertEqualsWithDelta(((11 / 255 + 0.055) / 1.055) ** 2.4, Color::fromHex('#0b0b0b')->luminance(), 1e-12);
    }

    public function testEveryBuiltInPaletteIsReadable(): void
    {
        self::assertSame(['default', 'pastel', 'vivid', 'earth', 'grayscale'], Palette::names());

        foreach (Palette::names() as $name) {
            $palette = Palette::named($name);

            foreach ($palette->backgrounds as $background) {
                self::assertGreaterThanOrEqual(4.5, $background->contrastWith($palette->inkFor($background)), $name.' '.$background->toHex());
            }
        }

        self::assertCount(12, Palette::named('default')->backgrounds);
    }

    public function testAnUnknownPaletteIsRefused(): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('The palettes are default, pastel, vivid, earth and grayscale.'));

        Palette::named('neon');
    }

    public function testTheInkOfTheHigherContrastIsChosen(): void
    {
        $palette = Palette::fromHex(['#ffffff', '#000000', '#767676']);

        self::assertSame('#111111', $palette->inkFor(Color::fromHex('#ffffff'))->toHex());
        self::assertSame('#ffffff', $palette->inkFor(Color::fromHex('#000000'))->toHex());
        self::assertSame('#ffffff', $palette->lightInk->toHex());
        self::assertSame('#111111', $palette->darkInk->toHex());
        self::assertSame('#000000', Palette::fromHex(['#ffffff'], '#eeeeee', '#000000')->inkFor(Color::fromHex('#ffffff'))->toHex());
    }

    public function testABackgroundReadableWithNeitherInkIsRefused(): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('The background #808080 reaches a contrast of 4.5:1 with neither ink.'));

        Palette::fromHex(['#ffffff', '#808080'], '#ffffff', '#202020');
    }

    public function testTheContrastOf45IsEnough(): void
    {
        // #767676 reaches 4.54:1 with white.
        self::assertCount(1, Palette::fromHex(['#767676'], '#ffffff', '#767676')->backgrounds);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidSizes(): iterable
    {
        yield 'empty' => [0];
        yield 'too many' => [65];
    }

    #[DataProvider('invalidSizes')]
    public function testAPaletteHoldsOneTo64Backgrounds(int $count): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('A palette holds 1 to 64 backgrounds.'));

        Palette::fromHex(array_fill(0, $count, '#000000'));
    }

    public function testAPaletteOf64Backgrounds(): void
    {
        self::assertCount(64, Palette::fromHex(array_fill(0, 64, '#000000'))->backgrounds);
    }
}
