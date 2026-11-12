<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Application;

use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\RenderBox;
use Phil\MediaBundle\Application\Transform\Thumbnail;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenderBox::class)]
final class RenderBoxTest extends TestCase
{
    public function testACropCoversTheBoxAroundTheFocalPoint(): void
    {
        $square = ImageFormat::of('square', 100, 100);

        self::assertSame([
            'centre' => [100, 0, 200, 200, 100, 100],
            'left' => [0, 0, 200, 200, 100, 100],
            'right' => [200, 0, 200, 200, 100, 100],
            'a third, down' => [20, 0, 200, 200, 100, 100],
            'two thirds, up' => [21, 0, 200, 200, 100, 100],
            'tall, a third' => [0, 20, 200, 200, 100, 100],
            'tall, two thirds' => [0, 21, 200, 200, 100, 100],
            'tall, bottom' => [0, 200, 200, 200, 100, 100],
            'not enlarged' => [0, 0, 40, 40, 40, 40],
            'wide box' => [0, 25, 400, 150, 400, 150],
        ], [
            'centre' => $this->box(400, 200, $square, 0.5, 0.5),
            'left' => $this->box(400, 200, $square, 0.0, 0.0),
            'right' => $this->box(400, 200, $square, 1.0, 1.0),
            'a third, down' => $this->box(400, 200, $square, 77 / 256, 0.5),
            'two thirds, up' => $this->box(400, 200, $square, 309 / 1024, 0.5),
            'tall, a third' => $this->box(200, 400, $square, 0.5, 77 / 256),
            'tall, two thirds' => $this->box(200, 400, $square, 0.5, 309 / 1024),
            'tall, bottom' => $this->box(200, 400, $square, 0.0, 1.0),
            'not enlarged' => $this->box(40, 40, $square, 0.5, 0.5),
            'wide box' => $this->box(400, 200, ImageFormat::of('wide', 800, 300), 0.5, 0.5),
        ]);
    }

    public function testTheKeptPartIsRounded(): void
    {
        self::assertSame([
            'up' => [0, 0, 3, 1, 3, 1],
            'up, tall' => [0, 0, 1, 3, 1, 3],
            'down' => [50, 0, 300, 300, 100, 100],
            'down, kept' => [0, 0, 33, 10, 10, 3],
            'down, kept tall' => [0, 0, 10, 33, 3, 10],
            'never nothing' => [200, 0, 1, 1, 1, 1],
            'never nothing, tall' => [0, 200, 1, 1, 1, 1],
        ], [
            'up' => $this->box(1000, 1, ImageFormat::of('a', 13, 5), 0.0, 0.0),
            'up, tall' => $this->box(1, 1000, ImageFormat::of('a', 5, 13), 0.0, 0.0),
            'down' => $this->box(400, 300, ImageFormat::of('a', 100, 100), 0.5, 0.5),
            'down, kept' => $this->box(100, 10, ImageFormat::of('a', 10, 3), 0.0, 0.0),
            'down, kept tall' => $this->box(10, 100, ImageFormat::of('a', 3, 10), 0.0, 0.0),
            'never nothing' => $this->box(400, 1, ImageFormat::of('a', 1, 1000), 0.5, 0.5),
            'never nothing, tall' => $this->box(1, 400, ImageFormat::of('a', 1000, 1), 0.5, 0.5),
        ]);
    }

    public function testAFitStaysInsideTheBox(): void
    {
        $fit = ImageFormat::of('fit', 100, 100, 'fit');

        self::assertSame([
            'wide' => [0, 0, 400, 200, 100, 50],
            'not enlarged' => [0, 0, 40, 20, 40, 20],
            'up' => [0, 0, 700, 300, 100, 43],
            'down' => [0, 0, 900, 400, 100, 44],
            'up, tall' => [0, 0, 300, 700, 43, 100],
            'down, tall' => [0, 0, 400, 900, 44, 100],
            'never nothing' => [0, 0, 1000, 2, 1, 1],
            'never nothing, tall' => [0, 0, 2, 1000, 1, 1],
        ], [
            'wide' => $this->box(400, 200, $fit, 0.0, 1.0),
            'not enlarged' => $this->box(40, 20, $fit, 0.5, 0.5),
            'up' => $this->box(700, 300, $fit, 0.5, 0.5),
            'down' => $this->box(900, 400, $fit, 0.5, 0.5),
            'up, tall' => $this->box(300, 700, $fit, 0.5, 0.5),
            'down, tall' => $this->box(400, 900, $fit, 0.5, 0.5),
            'never nothing' => $this->box(1000, 2, ImageFormat::of('fit', 1, 1, 'fit'), 0.5, 0.5),
            'never nothing, tall' => $this->box(2, 1000, ImageFormat::of('fit', 1, 1, 'fit'), 0.5, 0.5),
        ]);
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     *
     * @return list<int>
     */
    private function box(int $width, int $height, ImageFormat $format, float $x, float $y): array
    {
        $thumbnail = $format->transformers[0];
        self::assertInstanceOf(Thumbnail::class, $thumbnail);
        $box = RenderBox::fill($width, $height, $thumbnail->width, $thumbnail->height, $thumbnail->mode, [$x, $y]);

        return [$box->left, $box->top, $box->width, $box->height, $box->outputWidth, $box->outputHeight];
    }
}
