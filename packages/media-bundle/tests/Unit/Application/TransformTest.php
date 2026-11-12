<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application;

use Closure;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\Transform\Crop;
use Phil\MediaBundle\Application\Transform\Expand;
use Phil\MediaBundle\Application\Transform\Geometry;
use Phil\MediaBundle\Application\Transform\Heighten;
use Phil\MediaBundle\Application\Transform\Pixels;
use Phil\MediaBundle\Application\Transform\Resize;
use Phil\MediaBundle\Application\Transform\Step;
use Phil\MediaBundle\Application\Transform\Thumbnail;
use Phil\MediaBundle\Application\Transform\Transformer;
use Phil\MediaBundle\Application\Transform\Transformers;
use Phil\MediaBundle\Application\Transform\Widen;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Geometry::class)]
#[CoversClass(Step::class)]
#[CoversClass(Pixels::class)]
#[CoversClass(Thumbnail::class)]
#[CoversClass(Crop::class)]
#[CoversClass(Resize::class)]
#[CoversClass(Widen::class)]
#[CoversClass(Heighten::class)]
#[CoversClass(Expand::class)]
#[CoversClass(Transformers::class)]
#[CoversClass(ImageFormat::class)]
final class TransformTest extends TestCase
{
    public function testAGeometryKeepsItsFocalPointInside(): void
    {
        self::assertSame([40, 20, 0.0, 1.0], $this->geometry(Geometry::of(40, 20, -0.5, 1.5)));
        self::assertSame([40, 20, 0.5, 0.5], $this->geometry(Geometry::of(40, 20)));
        self::assertSame([1, 1, 0.25, 0.75], $this->geometry(Geometry::of(1, 1, 0.25, 0.75)));
    }

    public function testAStepCarriesTheFocalPoint(): void
    {
        $image = Geometry::of(400, 200, 0.25, 0.5);

        $same = Step::cut(0, 0, 400, 200, 400, 200);
        self::assertFalse($same->changes($image));
        self::assertSame([400, 200, 0.25, 0.5], $this->geometry($same->next($image)));
        self::assertSame([400, 200, 0.5, 0.0], $this->geometry(Step::cut(0, 50, 400, 100, 400, 200)->next(Geometry::of(400, 200, 0.5, 0.25))));
        self::assertSame([400, 200, 0.5, 1.0], $this->geometry(Step::cut(0, 50, 400, 100, 400, 200)->next(Geometry::of(400, 200, 0.5, 0.75))));
        self::assertSame([400, 200, 0.5, 0.5], $this->geometry(Step::cut(0, 50, 400, 100, 400, 200)->next(Geometry::of(400, 200, 0.5, 0.5))));

        $cut = Step::cut(50, 0, 200, 200, 100, 100);
        self::assertTrue($cut->changes($image));
        self::assertSame([50, 0, 200, 200, 0, 0, 100, 100, 100, 100, null], $this->step($cut));
        self::assertSame([100, 100, 0.25, 0.5], $this->geometry($cut->next($image)));
        self::assertSame([100, 100, 0.0, 0.5], $this->geometry(Step::cut(150, 0, 200, 200, 100, 100)->next($image)));

        $pad = Step::pad($image, 600, 400, 100, 50, 0xFF0000);
        self::assertSame([0, 0, 400, 200, 100, 50, 400, 200, 600, 400, 0xFF0000], $this->step($pad));
        self::assertSame([600, 400, 1 / 3, 0.375], $this->geometry($pad->next($image)));

        foreach ([[1, 0, 400, 200, 400, 200], [0, 1, 400, 200, 400, 200], [0, 0, 399, 200, 400, 200], [0, 0, 400, 199, 400, 200], [0, 0, 400, 200, 399, 200], [0, 0, 400, 200, 400, 199]] as [$left, $top, $width, $height, $outWidth, $outHeight]) {
            self::assertTrue(Step::cut($left, $top, $width, $height, $outWidth, $outHeight)->changes($image), implode(',', [$left, $top, $width, $height, $outWidth, $outHeight]));
        }

        self::assertTrue(Step::pad($image, 400, 200, 1, 0, null)->changes($image));
        self::assertTrue(Step::pad($image, 400, 200, 0, 1, null)->changes($image));
        self::assertTrue(Step::pad($image, 401, 200, 0, 0, null)->changes($image));
        self::assertTrue(Step::pad($image, 400, 201, 0, 0, null)->changes($image));
        self::assertFalse(Step::pad($image, 400, 200, 0, 0, 0xFFFFFF)->changes($image));
    }

    public function testAThumbnailCropsOrFits(): void
    {
        $image = Geometry::of(400, 200, 0.0, 0.5);

        self::assertSame([0, 0, 200, 200, 0, 0, 100, 100, 100, 100, null], $this->step(Thumbnail::of(100, 100)->step($image)));
        self::assertSame([0, 0, 400, 200, 0, 0, 100, 50, 100, 50, null], $this->step(Thumbnail::of(100, 100, 'fit')->step($image)));
        self::assertSame(['type' => 'thumbnail', 'width' => 100, 'height' => 50, 'mode' => 'fit'], Thumbnail::of(100, 50, 'fit')->definition());
        self::assertSame(['crop', 'fit'], Thumbnail::MODES);
        $this->refused(static fn (): Thumbnail => Thumbnail::of(1, 1, 'stretch'), 'A thumbnail crops or fits; "stretch" is neither.');
    }

    public function testACropCutsAroundTheFocalPointOrWhereItIsTold(): void
    {
        $image = Geometry::of(400, 200, 0.25, 0.5);

        self::assertSame([50, 50, 100, 100, 0, 0, 100, 100, 100, 100, null], $this->step(Crop::of(100, 100)->step($image)));
        self::assertSame([0, 0, 400, 200, 0, 0, 400, 200, 400, 200, null], $this->step(Crop::of(800, 800)->step($image)));
        self::assertSame([300, 100, 100, 100, 0, 0, 100, 100, 100, 100, null], $this->step(Crop::of(100, 100, 350, 120)->step($image)));
        self::assertSame([10, 20, 100, 100, 0, 0, 100, 100, 100, 100, null], $this->step(Crop::of(100, 100, 10, 20)->step($image)));
        self::assertSame([0, 0, 100, 100, 0, 0, 100, 100, 100, 100, null], $this->step(Crop::of(100, 100, -10, -20)->step($image)));
        self::assertSame([0, 0, 100, 100, 0, 0, 100, 100, 100, 100, null], $this->step(Crop::of(100, 100)->step(Geometry::of(400, 200, 0.0, 0.0))));
        self::assertSame([300, 100, 100, 100, 0, 0, 100, 100, 100, 100, null], $this->step(Crop::of(100, 100)->step(Geometry::of(400, 200, 1.0, 1.0))));
        self::assertSame([51, 0, 100, 100, 0, 0, 100, 100, 100, 100, null], $this->step(Crop::of(100, 100)->step(Geometry::of(400, 100, 0.2525, 0.5))));
        self::assertSame(['type' => 'crop', 'width' => 100, 'height' => 50, 'x' => 0, 'y' => null], Crop::of(100, 50, -3)->definition());
        self::assertSame(['type' => 'crop', 'width' => 100, 'height' => 50, 'x' => null, 'y' => 0], Crop::of(100, 50, null, -3)->definition());
        self::assertSame(['type' => 'crop', 'width' => 100, 'height' => 50, 'x' => 7, 'y' => 9], Crop::of(100, 50, 7, 9)->definition());
    }

    public function testAResizeChangesTheProportions(): void
    {
        $image = Geometry::of(400, 200);

        self::assertSame([0, 0, 400, 200, 0, 0, 100, 100, 100, 100, null], $this->step(Resize::of(100, 100)->step($image)));
        self::assertSame([0, 0, 400, 200, 0, 0, 400, 200, 400, 200, null], $this->step(Resize::of(800, 800)->step($image)));
        self::assertSame([0, 0, 400, 200, 0, 0, 800, 100, 800, 100, null], $this->step(Resize::of(800, 100, true)->step($image)));
        self::assertSame([0, 0, 400, 200, 0, 0, 100, 800, 100, 800, null], $this->step(Resize::of(100, 800, true)->step($image)));
        self::assertSame(['type' => 'resize', 'width' => 1, 'height' => 2, 'upscale' => false], Resize::of(1, 2)->definition());
        self::assertSame(['type' => 'resize', 'width' => 1, 'height' => 2, 'upscale' => true], Resize::of(1, 2, true)->definition());
    }

    public function testWidenAndHeightenKeepTheProportions(): void
    {
        $image = Geometry::of(400, 200);

        self::assertSame([0, 0, 400, 200, 0, 0, 100, 50, 100, 50, null], $this->step(Widen::of(100)->step($image)));
        self::assertFalse(Widen::of(400)->step($image)->changes($image));
        self::assertFalse(Widen::of(800)->step($image)->changes($image));
        self::assertSame([0, 0, 400, 200, 0, 0, 800, 400, 800, 400, null], $this->step(Widen::of(800, true)->step($image)));
        self::assertSame([0, 0, 400, 200, 0, 0, 3, 2, 3, 2, null], $this->step(Widen::of(3)->step($image)));
        self::assertSame([0, 0, 400, 210, 0, 0, 4, 2, 4, 2, null], $this->step(Widen::of(4)->step(Geometry::of(400, 210))));
        self::assertSame([0, 0, 400, 200, 0, 0, 400, 200, 400, 200, null], $this->step(Widen::of(800)->step($image)));
        self::assertSame([1, 1], [Widen::of(1)->step(Geometry::of(400, 1))->canvasWidth, Widen::of(1)->step(Geometry::of(400, 1))->canvasHeight]);
        self::assertSame(['type' => 'widen', 'width' => 5, 'upscale' => true], Widen::of(5, true)->definition());
        self::assertSame(['type' => 'widen', 'width' => 5, 'upscale' => false], Widen::of(5)->definition());

        self::assertSame([0, 0, 400, 200, 0, 0, 200, 100, 200, 100, null], $this->step(Heighten::of(100)->step($image)));
        self::assertFalse(Heighten::of(200)->step($image)->changes($image));
        self::assertFalse(Heighten::of(300)->step($image)->changes($image));
        self::assertSame([0, 0, 400, 200, 0, 0, 800, 400, 800, 400, null], $this->step(Heighten::of(400, true)->step($image)));
        self::assertSame([0, 0, 200, 400, 0, 0, 2, 3, 2, 3, null], $this->step(Heighten::of(3)->step(Geometry::of(200, 400))));
        self::assertSame([0, 0, 210, 400, 0, 0, 2, 4, 2, 4, null], $this->step(Heighten::of(4)->step(Geometry::of(210, 400))));
        self::assertSame([0, 0, 400, 200, 0, 0, 400, 200, 400, 200, null], $this->step(Heighten::of(300)->step($image)));
        self::assertSame([1, 1], [Heighten::of(1)->step(Geometry::of(1, 400))->canvasWidth, Heighten::of(1)->step(Geometry::of(1, 400))->canvasHeight]);
        self::assertSame(['type' => 'heighten', 'height' => 5, 'upscale' => true], Heighten::of(5, true)->definition());
        self::assertSame(['type' => 'heighten', 'height' => 5, 'upscale' => false], Heighten::of(5)->definition());
    }

    public function testAnExpansionAddsAMargin(): void
    {
        $image = Geometry::of(400, 200);

        self::assertSame([0, 0, 400, 200, 100, 101, 400, 200, 600, 403, null], $this->step(Expand::of(600, 403)->step($image)));
        self::assertSame([0, 0, 400, 200, 0, 0, 400, 200, 400, 200, null], $this->step(Expand::of(100, 100)->step($image)));
        self::assertSame([0, 0, 400, 200, 200, 200, 400, 200, 600, 400, 0x00FF7F], $this->step(Expand::of(600, 400, 900, 900, '#00FF7f')->step($image)));
        self::assertSame([0, 0, 400, 200, 10, 0, 400, 200, 600, 400, null], $this->step(Expand::of(600, 400, 10, -5)->step($image)));
        self::assertSame([0, 0, 400, 200, 0, 20, 400, 200, 600, 400, null], $this->step(Expand::of(600, 400, -5, 20)->step($image)));
        self::assertSame(['type' => 'expand', 'width' => 6, 'height' => 4, 'x' => 0, 'y' => 2, 'background' => '#abcdef'], Expand::of(6, 4, -1, 2, '#ABCDEF')->definition());
        self::assertSame(0xABCDEF, Expand::of(600, 400, background: '#ABCDEF')->step($image)->background);
        self::assertSame(['type' => 'expand', 'width' => 6, 'height' => 4, 'x' => 1, 'y' => 0, 'background' => null], Expand::of(6, 4, 1, -2)->definition());
        $this->refused(static fn (): Expand => Expand::of(1, 1, background: 'red'), 'A background is a colour "#rrggbb"; "red" is not.');
        $this->refused(static fn (): Expand => Expand::of(1, 1, background: "#000000\n"), "A background is a colour \"#rrggbb\"; \"#000000\n\" is not.");
        $this->refused(static fn (): Expand => Expand::of(1, 1, background: 'x#000000'), 'A background is a colour "#rrggbb"; "x#000000" is not.');
    }

    public function testPixelsAreBounded(): void
    {
        self::assertSame([1, 8000, 8000], [Pixels::check(1), Pixels::check(8000), Pixels::MAX]);

        foreach ([0, 8001] as $pixels) {
            $this->refused(static fn (): int => Pixels::check($pixels), \sprintf('A transformation is 1 to 8000 pixels across and down; %d is not.', $pixels));
        }

        self::assertSame([3, 0, 6], [Pixels::offset(3, 0.5, 10, 4), Pixels::offset(-3, 0.5, 10, 4), Pixels::offset(9, 0.5, 10, 4)]);
        self::assertSame([4, 2], [Pixels::offset(null, 0.5, 10, 3), Pixels::offset(null, 0.33, 10, 2)]);
    }

    public function testTransformationsAreReadFromTheirDefinitions(): void
    {
        $definitions = [
            ['type' => 'thumbnail', 'width' => 10, 'height' => 20],
            ['type' => 'thumbnail', 'width' => 10, 'height' => 20, 'mode' => 'fit'],
            ['type' => 'crop', 'width' => 10, 'height' => 20, 'x' => 1, 'y' => 2],
            ['type' => 'resize', 'width' => 10, 'height' => 20, 'upscale' => true],
            ['type' => 'resize', 'width' => 10, 'height' => 20, 'upscale' => 1],
            ['type' => 'widen', 'width' => 10],
            ['type' => 'heighten', 'height' => 20, 'upscale' => true],
            ['type' => 'expand', 'width' => 10, 'height' => 20, 'x' => 1, 'y' => 2, 'background' => '#000000'],
            ['type' => 'expand', 'width' => 10, 'height' => 20, 'x' => '1', 'background' => 0],
        ];

        self::assertSame([
            ['type' => 'thumbnail', 'width' => 10, 'height' => 20, 'mode' => 'crop'],
            ['type' => 'thumbnail', 'width' => 10, 'height' => 20, 'mode' => 'fit'],
            ['type' => 'crop', 'width' => 10, 'height' => 20, 'x' => 1, 'y' => 2],
            ['type' => 'resize', 'width' => 10, 'height' => 20, 'upscale' => true],
            ['type' => 'resize', 'width' => 10, 'height' => 20, 'upscale' => false],
            ['type' => 'widen', 'width' => 10, 'upscale' => false],
            ['type' => 'heighten', 'height' => 20, 'upscale' => true],
            ['type' => 'expand', 'width' => 10, 'height' => 20, 'x' => 1, 'y' => 2, 'background' => '#000000'],
            ['type' => 'expand', 'width' => 10, 'height' => 20, 'x' => null, 'y' => null, 'background' => null],
        ], array_map(static fn (Transformer $transformer): array => $transformer->definition(), Transformers::fromDefinitions($definitions)));
        self::assertSame(['thumbnail', 'crop', 'resize', 'widen', 'heighten', 'expand'], Transformers::TYPES);

        foreach ([
            [['type' => 'blur'], 'A transformation is one of thumbnail, crop, resize, widen, heighten, expand; "blur" is not.'],
            [[], 'A transformation is one of thumbnail, crop, resize, widen, heighten, expand; "null" is not.'],
            [['type' => 'thumbnail', 'height' => 1], 'A "thumbnail" transformation needs a width.'],
            [['type' => 'thumbnail', 'width' => 1], 'A "thumbnail" transformation needs a height.'],
            [['type' => 'crop', 'height' => 1], 'A "crop" transformation needs a width.'],
            [['type' => 'crop', 'width' => 1], 'A "crop" transformation needs a height.'],
            [['type' => 'resize', 'height' => 1], 'A "resize" transformation needs a width.'],
            [['type' => 'resize', 'width' => 1], 'A "resize" transformation needs a height.'],
            [['type' => 'widen'], 'A "widen" transformation needs a width.'],
            [['type' => 'heighten'], 'A "heighten" transformation needs a height.'],
            [['type' => 'expand', 'height' => 1], 'A "expand" transformation needs a width.'],
            [['type' => 'expand', 'width' => 1], 'A "expand" transformation needs a height.'],
        ] as [$definition, $message]) {
            $this->refused(static fn (): Transformer => Transformers::fromDefinition($definition), $message);
        }
    }

    public function testAFormatAppliesItsTransformationsInOrder(): void
    {
        $format = ImageFormat::transforming('card', [Crop::of(300, 300), Widen::of(100), Expand::of(120, 120)], 70, [80, 40, 80]);

        self::assertSame(['card', 70, [40, 80]], [$format->name, $format->quality, $format->widths]);
        self::assertSame([120, 120, 0.5, 0.5], $this->geometry($format->apply(Geometry::of(400, 300))));
        self::assertSame([200, 100, 0.0, 1.0], $this->geometry(ImageFormat::transforming('none', [])->apply(Geometry::of(200, 100, 0.0, 1.0))));

        $narrow = $format->atWidth(40);
        self::assertSame(['card', 70, []], [$narrow->name, $narrow->quality, $narrow->widths]);
        self::assertSame(['type' => 'widen', 'width' => 40, 'upscale' => false], $narrow->transformers[3]->definition());
        self::assertSame([40, 40], [$narrow->apply(Geometry::of(400, 300))->width, $narrow->apply(Geometry::of(400, 300))->height]);
        $this->refused(static fn (): ImageFormat => $format->atWidth(60), 'The format "card" is not rendered 60 pixels wide.');
        $this->refused(static fn (): ImageFormat => ImageFormat::transforming('original', []), 'A format is 1 to 32 lower-case letters, digits or "_", and not "original"; "original" is not.');
        $this->refused(static fn (): ImageFormat => ImageFormat::transforming('a', [], 0), 'A quality is from 1 to 100.');
        $this->refused(static fn (): ImageFormat => ImageFormat::transforming('a', [], 80, [0]), 'A transformation is 1 to 8000 pixels across and down; 0 is not.');
        self::assertSame([[100, 300], 80], [ImageFormat::of('a', 1, 1, widths: [300, 100])->widths, ImageFormat::transforming('a', [])->quality]);

        $original = ImageFormat::original(60, [640, 320]);
        self::assertSame(['original', [], 60, [320, 640]], [$original->name, $original->transformers, $original->quality, $original->widths]);
        self::assertSame(['original', [], 80, []], [ImageFormat::original()->name, ImageFormat::original()->transformers, ImageFormat::original()->quality, ImageFormat::original()->widths]);
        $this->refused(static fn (): ImageFormat => ImageFormat::original(101), 'A quality is from 1 to 100.');
    }

    /**
     * @return array{int, int, float, float}
     */
    private function geometry(Geometry $geometry): array
    {
        return [$geometry->width, $geometry->height, $geometry->focalX, $geometry->focalY];
    }

    /**
     * @return list<int|null>
     */
    private function step(Step $step): array
    {
        return [$step->sourceLeft, $step->sourceTop, $step->sourceWidth, $step->sourceHeight, $step->targetLeft, $step->targetTop, $step->targetWidth, $step->targetHeight, $step->canvasWidth, $step->canvasHeight, $step->background];
    }

    private function refused(Closure $make, string $message): void
    {
        try {
            $make();
            self::fail($message);
        } catch (InvalidMedia $invalidMedia) {
            self::assertSame($message, $invalidMedia->getMessage());
        }
    }
}
