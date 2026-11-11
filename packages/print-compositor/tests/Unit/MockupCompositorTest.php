<?php

declare(strict_types=1);

namespace Phil\PrintCompositor\Tests\Unit;

use Phil\PrintCompositor\FacePhoto;
use Phil\PrintCompositor\HorizontalAlign;
use Phil\PrintCompositor\Internal\Gd;
use Phil\PrintCompositor\Mockup;
use Phil\PrintCompositor\MockupCompositor;
use Phil\PrintCompositor\PixelBox;
use Phil\PrintCompositor\Placement;
use Phil\PrintCompositor\Raster;
use Phil\PrintCompositor\Tests\Unit\Fixture\Images;
use Phil\PrintCompositor\VerticalAlign;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresFunction;
use PHPUnit\Framework\TestCase;

/**
 * Photos of grey columns, whose luminance is their grey, and artworks of a few pixels placed on them.
 */
#[CoversClass(MockupCompositor::class)]
#[CoversClass(Gd::class)]
#[CoversClass(Mockup::class)]
#[CoversClass(FacePhoto::class)]
#[CoversClass(Placement::class)]
#[CoversClass(PixelBox::class)]
#[CoversClass(Raster::class)]
#[CoversClass(HorizontalAlign::class)]
#[CoversClass(VerticalAlign::class)]
final class MockupCompositorTest extends TestCase
{
    private const int ARTWORK = 0x005A3C1E;

    public function testTheFabricShadingDarkensAndLightensThePrint(): void
    {
        $result = $this->compose(1.0);

        self::assertSame([
            [$this->grey(50), 0x003C2814, 0x00785028, $this->grey(50)],
            [$this->grey(50), $this->grey(100), 0x00896A4A, $this->grey(50)],
        ], Images::pixels($result), 'Against the mean of the zone, 150, the column of 100 takes two thirds of the colour and the column of 200 four thirds; the transparent pixel leaves the photo, the translucent one blends with it; outside the zone, nothing changes.');
    }

    public function testTheLuminanceWeighsEachChannel(): void
    {
        $photo = Images::raster([[0x000AC81E, 0x00C93363], [0x000AC81E, 0x00C93363]]);
        $artwork = Images::raster([[0x005B3D21, 0x005B3D21], [0x7F5B3D21, 0x1B5B3D21]]);
        $result = new MockupCompositor()->compose(new Mockup(new FacePhoto('front', 'mixed', $photo, ['chest' => new PixelBox(0, 0, 2, 2)]), [new Placement('chest', $artwork)], 1.0));

        self::assertSame([[0x00734D2A, 0x00432D18], [0x000AC81E, 0x00602E28]], Images::pixels($result), 'Lit at 147.33 and 86.36 against a mean of 116.84, the pixels take 1.26 and 0.74 of the colour.');
    }

    public function testTheShadingStrengthScalesTheEffect(): void
    {
        self::assertSame([0x004B3219, 0x00694623], \array_slice(Images::pixels($this->compose(0.5))[0], 1, 2), 'Half the strength: five sixths and seven sixths of the colour.');
        self::assertSame([self::ARTWORK, self::ARTWORK], \array_slice(Images::pixels($this->compose(0.0))[0], 1, 2), 'No shading: the colour as drawn.');
        self::assertSame([0x00422C16, 0x00724C26], \array_slice(Images::pixels($this->compose(Mockup::DEFAULT_SHADING))[0], 1, 2));
    }

    public function testAHighlightLightensAPrintTwiceAtMostAndAChannelStopsAt255(): void
    {
        $photo = Images::raster([[$this->grey(0), $this->grey(0), $this->grey(255)], [$this->grey(0), $this->grey(0), $this->grey(255)]]);
        $artwork = Images::raster([[0x00323232, 0x00323232, 0x00323232], [0x00C80A0A, 0x00C80A0A, 0x00C80A0A]]);
        $result = new MockupCompositor()->compose(new Mockup(new FacePhoto('front', 'white', $photo, ['chest' => new PixelBox(0, 0, 3, 2)]), [new Placement('chest', $artwork)], 1.0));

        self::assertSame([[0x00000000, 0x00000000, 0x00646464], [0x00000000, 0x00000000, 0x00FF1414]], Images::pixels($result), 'The white column is three times the mean, but lightens twice.');
    }

    public function testABlackZoneLeavesThePrintAsDrawn(): void
    {
        $photo = Images::raster([[$this->grey(0), $this->grey(0)]]);
        $result = new MockupCompositor()->compose(new Mockup(new FacePhoto('front', 'black', $photo, ['chest' => new PixelBox(0, 0, 2, 1)]), [new Placement('chest', Images::raster([[self::ARTWORK, self::ARTWORK]]))], 1.0));

        self::assertSame([[self::ARTWORK, self::ARTWORK]], Images::pixels($result));
    }

    public function testThePhotoKeepsItsTransparency(): void
    {
        $photo = Images::raster([[0x40646464]]);
        $result = new MockupCompositor()->compose(new Mockup(new FacePhoto('front', 'grey', $photo, ['chest' => new PixelBox(0, 0, 1, 1)]), [new Placement('chest', Images::raster([[self::ARTWORK]]))], 1.0));

        self::assertSame([[0x405A3C1E]], Images::pixels($result));
    }

    public function testTheArtworkIsAlignedInItsZoneAndShadedByTheWholeZone(): void
    {
        $photo = Images::raster([[$this->grey(100), $this->grey(100)], [$this->grey(200), $this->grey(200)]]);
        $mockup = new Mockup(
            new FacePhoto('front', 'grey', $photo, ['chest' => new PixelBox(0, 0, 2, 2)]),
            [new Placement('chest', Images::raster([[self::ARTWORK, self::ARTWORK]]), HorizontalAlign::Centre, VerticalAlign::Bottom)],
            1.0,
        );

        self::assertSame([[$this->grey(100), $this->grey(100)], [0x00785028, 0x00785028]], Images::pixels(new MockupCompositor()->compose($mockup)));
    }

    public function testEveryPlacementIsPrintedAndThePhotoIsLeftAsItWas(): void
    {
        $photo = Images::raster([[$this->grey(100), $this->grey(100)]]);
        $mockup = new Mockup(
            new FacePhoto('front', 'grey', $photo, ['left' => new PixelBox(0, 0, 1, 1), 'right' => new PixelBox(1, 0, 1, 1)]),
            [new Placement('left', Images::raster([[self::ARTWORK]])), new Placement('right', Images::raster([[0x00FF0000]]))],
        );
        $result = new MockupCompositor()->compose($mockup);

        self::assertSame([[self::ARTWORK, 0x00FF0000]], Images::pixels($result));
        self::assertSame([[$this->grey(100), $this->grey(100)]], Images::pixels($photo));
    }

    #[RequiresFunction('imagewebp')]
    public function testAMockupIsEncodedInWebpForASite(): void
    {
        $result = $this->compose(1.0);

        self::assertInstanceOf(Raster::class, $result);
        self::assertSame('WEBP', substr($result->webp(), 8, 4));
    }

    private function compose(float $shading): Raster
    {
        $photo = Images::raster([
            [$this->grey(50), $this->grey(100), $this->grey(200), $this->grey(50)],
            [$this->grey(50), $this->grey(100), $this->grey(200), $this->grey(50)],
        ]);
        $artwork = Images::raster([[self::ARTWORK, self::ARTWORK], [0x7F5A3C1E, 0x1B5A3C1E]]);

        return new MockupCompositor()->compose(new Mockup(new FacePhoto('front', 'grey', $photo, ['chest' => new PixelBox(1, 0, 2, 2)]), [new Placement('chest', $artwork)], $shading));
    }

    private function grey(int $value): int
    {
        return ($value << 16) | ($value << 8) | $value;
    }
}
