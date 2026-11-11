<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit;

use Phil\PrintCompositor\Box;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\Exception\InvalidLayout;
use Phil\PrintCompositor\Exception\PrintCompositorException;
use Phil\PrintCompositor\Fit;
use Phil\PrintCompositor\HorizontalAlign;
use Phil\PrintCompositor\PixelBox;
use Phil\PrintCompositor\Units;
use Phil\PrintCompositor\VerticalAlign;
use Phil\PrintCompositor\Zone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Colour::class)]
#[CoversClass(Box::class)]
#[CoversClass(PixelBox::class)]
#[CoversClass(Zone::class)]
#[CoversClass(Units::class)]
#[CoversClass(HorizontalAlign::class)]
#[CoversClass(VerticalAlign::class)]
#[CoversClass(Fit::class)]
#[CoversClass(InvalidLayout::class)]
final class ValuesTest extends TestCase
{
    public function testAColourIsReadFromHex(): void
    {
        $colour = Colour::fromHex('#0A80fF');

        self::assertSame([10, 128, 255, 255], [$colour->red, $colour->green, $colour->blue, $colour->alpha]);
        self::assertSame('#0a80ff', $colour->toHex());
    }

    public function testAColourMayCarryItsAlphaAndOmitItsHash(): void
    {
        $colour = Colour::fromHex('10203040');

        self::assertSame([16, 32, 48, 64], [$colour->red, $colour->green, $colour->blue, $colour->alpha]);
        self::assertSame('#10203040', $colour->toHex());
        self::assertSame('#102030fe', new Colour(16, 32, 48, 254)->toHex());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badHex(): iterable
    {
        yield 'short' => ['#fff'];
        yield 'not hex' => ['#gg0000'];
        yield 'trailing' => ["#ff0000\n"];
        yield 'leading' => ['x#ff0000'];
        yield 'seven digits' => ['#ff00001'];
    }

    #[DataProvider('badHex')]
    public function testAColourIsWrittenInSixOrEightDigits(string $hex): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A colour is written #RRGGBB or #RRGGBBAA; "%s" given.', $hex));

        Colour::fromHex($hex);
    }

    /**
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function badChannels(): iterable
    {
        yield 'red below' => [-1, 0, 0, 255, 'The red channel of a colour goes from 0 to 255; -1 given.'];
        yield 'green above' => [0, 256, 0, 255, 'The green channel of a colour goes from 0 to 255; 256 given.'];
        yield 'blue below' => [0, 0, -1, 255, 'The blue channel of a colour goes from 0 to 255; -1 given.'];
        yield 'alpha above' => [0, 0, 0, 256, 'The alpha channel of a colour goes from 0 to 255; 256 given.'];
    }

    #[DataProvider('badChannels')]
    public function testEachChannelGoesFromZeroTo255(int $red, int $green, int $blue, int $alpha, string $message): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Colour($red, $green, $blue, $alpha);
    }

    public function testTheLimitsOfAChannelAreAllowed(): void
    {
        $colour = new Colour(0, 255, 0, 0);

        self::assertSame('#00ff0000', $colour->toHex());
        self::assertSame(255, new Colour(1, 2, 3)->alpha, 'A colour is opaque by default.');
    }

    public function testABoxTurnsIntoPixelsEdgeByEdge(): void
    {
        $box = new Box(2.54, 5.08, 10.16, 1.27);

        self::assertEquals(new PixelBox(10, 20, 40, 5), $box->toPixels(100));
        self::assertEquals(new PixelBox(1, 1, 1, 1), new Box(0.3, 0.3, 0.05, 0.05)->toPixels(100), 'A box keeps at least one pixel either way.');
        self::assertEquals(new PixelBox(1, 1, 1, 1), new Box(0.14, 0.14, 0.4, 0.4)->toPixels(100), 'Each edge is rounded on its own: 0.55 to 2.13 is 1 to 2, not 1.57 pixels rounded to 2.');
    }

    public function testABoxLiesInsideAZoneEdgesIncluded(): void
    {
        $box = new Box(10, 20, 30, 40);

        self::assertTrue($box->isInside(40, 60));
        self::assertFalse($box->isInside(39.9, 60));
        self::assertFalse($box->isInside(40, 59.9));
        self::assertTrue(new Box(0, 0, 1, 1)->isInside(1, 1));
    }

    /**
     * @return iterable<string, array{float, float, float, float, string}>
     */
    public static function badBoxes(): iterable
    {
        yield 'left' => [-0.1, 0, 1, 1, 'A box starts inside its zone; it starts at -0.1 × 0 mm.'];
        yield 'top' => [0, -0.1, 1, 1, 'A box starts inside its zone; it starts at 0 × -0.1 mm.'];
        yield 'width' => [0, 0, 0, 1, 'A box has a positive size; 0 × 1 mm given.'];
        yield 'height' => [0, 0, 1, 0, 'A box has a positive size; 1 × 0 mm given.'];
    }

    #[DataProvider('badBoxes')]
    public function testABoxStartsInsideItsZoneAndHasASize(float $x, float $y, float $width, float $height, string $message): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Box($x, $y, $width, $height);
    }

    public function testAPixelBoxLiesInsideAnImageEdgesIncluded(): void
    {
        $box = new PixelBox(1, 2, 3, 4);

        self::assertTrue($box->isInside(4, 6));
        self::assertFalse($box->isInside(3, 6));
        self::assertFalse($box->isInside(4, 5));
        self::assertTrue(new PixelBox(0, 0, 1, 1)->isInside(1, 1));
    }

    /**
     * @return iterable<string, array{int, int, int, int, string}>
     */
    public static function badPixelBoxes(): iterable
    {
        yield 'left' => [-1, 0, 1, 1, 'A pixel box starts inside its image; it starts at -1 × 0.'];
        yield 'top' => [0, -1, 1, 1, 'A pixel box starts inside its image; it starts at 0 × -1.'];
        yield 'width' => [0, 0, 0, 1, 'A pixel box has a positive size; 0 × 1 given.'];
        yield 'height' => [0, 0, 1, 0, 'A pixel box has a positive size; 1 × 0 given.'];
    }

    #[DataProvider('badPixelBoxes')]
    public function testAPixelBoxStartsInsideItsImageAndHasASize(int $x, int $y, int $width, int $height, string $message): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains($message);

        new PixelBox($x, $y, $width, $height);
    }

    public function testAZoneHasANameAndARealSize(): void
    {
        $zone = new Zone('front', 254, 25.4);

        self::assertSame([3000, 300], $zone->pixelSize(300));
        self::assertSame([1, 1], new Zone('tiny', 0.01, 0.01)->pixelSize(300), 'A zone keeps at least one pixel either way.');
    }

    /**
     * @return iterable<string, array{string, float, float, string}>
     */
    public static function badZones(): iterable
    {
        yield 'no name' => [' ', 1, 1, 'A zone has a name.'];
        yield 'width' => ['front', 0, 1, 'The zone "front" has a positive size; 0 × 1 mm given.'];
        yield 'height' => ['front', 1, -1, 'The zone "front" has a positive size; 1 × -1 mm given.'];
        yield 'no height' => ['front', 1, 0, 'The zone "front" has a positive size; 1 × 0 mm given.'];
    }

    #[DataProvider('badZones')]
    public function testAZoneIsRefusedWithoutANameOrASize(string $name, float $width, float $height, string $message): void
    {
        $this->expectException(InvalidLayout::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Zone($name, $width, $height);
    }

    public function testUnitsTurnIntoPixels(): void
    {
        self::assertSame(300, Units::pixels(25.4, 300));
        self::assertSame(2, Units::pixels(0.42, 100), '1.65 pixels round to 2.');
        self::assertSame(1, Units::pixels(0.38, 100), '1.496 pixels round to 1.');
        self::assertSame(50.0, Units::pointsToPixels(12, 300));
    }

    public function testAlignmentsOffsetWhatTheyAlign(): void
    {
        self::assertSame(0, HorizontalAlign::Left->offset(10, 4));
        self::assertSame(3, HorizontalAlign::Centre->offset(10, 4));
        self::assertSame(2, HorizontalAlign::Centre->offset(10, 5), 'An odd difference leans to the left.');
        self::assertSame(6, HorizontalAlign::Right->offset(10, 4));
        self::assertSame(0, VerticalAlign::Top->offset(10, 4));
        self::assertSame(3, VerticalAlign::Middle->offset(10, 4));
        self::assertSame(2, VerticalAlign::Middle->offset(10, 5), 'An odd difference leans to the top.');
        self::assertSame(6, VerticalAlign::Bottom->offset(10, 4));
        self::assertSame(['contain', 'cover'], array_map(static fn (Fit $fit): string => $fit->value, Fit::cases()));
    }

    public function testEveryExceptionIsOneOfTheLibrary(): void
    {
        self::assertInstanceOf(PrintCompositorException::class, InvalidLayout::because('reason'));
        self::assertSame('reason', InvalidLayout::because('reason')->getMessage());
    }
}
