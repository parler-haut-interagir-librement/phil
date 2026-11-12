<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Colour;

use Phil\AccessibilityCheck\Colour\Colour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Colour::class)]
final class ColourTest extends TestCase
{
    public function testTheLuminanceGoesFromBlackToWhite(): void
    {
        self::assertSame(0.0, new Colour(0, 0, 0)->luminance());
        self::assertEqualsWithDelta(1.0, new Colour(255, 255, 255)->luminance(), 1e-9);
        self::assertEqualsWithDelta(0.2126, new Colour(255, 0, 0)->luminance(), 1e-9);
        self::assertEqualsWithDelta(0.7152, new Colour(0, 255, 0)->luminance(), 1e-9);
        self::assertEqualsWithDelta(0.0722, new Colour(0, 0, 255)->luminance(), 1e-9);
    }

    public function testDarkChannelsAreLinearAndLightOnesFollowTheCurve(): void
    {
        self::assertEqualsWithDelta(10 / 255 / 12.92, new Colour(10, 0, 0)->luminance() / 0.2126, 1e-12);
        self::assertEqualsWithDelta(((11 / 255 + 0.055) / 1.055) ** 2.4, new Colour(11, 0, 0)->luminance() / 0.2126, 1e-12);
        self::assertEqualsWithDelta(0.21586, new Colour(128, 128, 128)->luminance(), 1e-5);
        self::assertSame(0.2126 * (0.04045 / 12.92), new Colour(10.31475, 0, 0)->luminance());
    }

    public function testTheContrastRatioIsTheSameEitherWay(): void
    {
        $black = new Colour(0, 0, 0);
        $white = new Colour(255, 255, 255);

        self::assertEqualsWithDelta(21.0, $black->contrastWith($white), 1e-9);
        self::assertEqualsWithDelta(21.0, $white->contrastWith($black), 1e-9);
        self::assertSame(1.0, $white->contrastWith($white));
        self::assertEqualsWithDelta(2.85, new Colour(153, 153, 153)->contrastWith($white), 0.01);
    }

    public function testATranslucentColourIsLaidOverWhatIsBehind(): void
    {
        $half = new Colour(0, 0, 0, 0.5)->over(new Colour(255, 255, 255));

        self::assertSame([127.5, 127.5, 127.5, 1.0], [$half->red, $half->green, $half->blue, $half->alpha]);

        $quarter = new Colour(100, 40, 200, 0.25)->over(new Colour(20, 80, 0));

        self::assertEqualsWithDelta([40.0, 70.0, 50.0], [$quarter->red, $quarter->green, $quarter->blue], 1e-9);

        $opaque = new Colour(10, 20, 30)->over(new Colour(255, 255, 255));

        self::assertSame([10.0, 20.0, 30.0], [$opaque->red, $opaque->green, $opaque->blue]);
    }

    public function testOnlyAFullOpacityIsOpaque(): void
    {
        self::assertTrue(new Colour(0, 0, 0)->isOpaque());
        self::assertFalse(new Colour(0, 0, 0, 0.99)->isOpaque());
    }

    public function testTheHexadecimalFormRoundsTheChannels(): void
    {
        self::assertSame('#0080ff', new Colour(0, 127.5, 255)->toHex());
        self::assertSame('#0b141f', new Colour(10.6, 20.4, 30.6)->toHex());
        self::assertSame('#0a151e', new Colour(10.4, 20.6, 30.4)->toHex());
    }
}
