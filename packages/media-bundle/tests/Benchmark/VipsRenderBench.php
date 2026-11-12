<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Benchmark;

use Generator;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Infrastructure\Image\VipsImageProcessor;

/**
 * GD and libvips on the same first rendering: a 400 × 300 WebP thumbnail of a JPEG photo. GD decodes the whole original
 * in PHP's process; libvips streams it in tiles in its own processes, at the cost of starting them. Without libvips on
 * the `PATH`, `benchVips` measures nothing and says so in its name only: compare it once libvips is installed.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"media"})
 */
final class VipsRenderBench
{
    private readonly GdImageProcessor $gd;

    private readonly ?VipsImageProcessor $vips;

    private readonly ImageFormat $thumbnail;

    private string $image = '';

    public function __construct()
    {
        $this->gd = new GdImageProcessor(100_000_000);
        $this->vips = VipsImageProcessor::find(100_000_000);
        $this->thumbnail = ImageFormat::of('thumbnail', 400, 300);
    }

    /**
     * @param array{width: int<1, max>, height: int<1, max>} $params
     */
    public function setUp(array $params): void
    {
        $canvas = imagecreatetruecolor($params['width'], $params['height']);

        if (false === $canvas) {
            return;
        }

        for ($y = 0; $y < $params['height']; $y += 8) {
            imagefilledrectangle($canvas, 0, $y, $params['width'] - 1, $y + 7, (int) imagecolorallocate($canvas, max(0, $y % 256), max(0, intdiv($y, 3) % 256), 128));
        }

        ob_start();
        imagejpeg($canvas, null, 85);
        $this->image = (string) ob_get_clean();
    }

    /**
     * @ParamProviders("originals")
     *
     * @Revs(2)
     *
     * @Iterations(3)
     */
    public function benchGd(): void
    {
        $this->gd->render($this->image, $this->thumbnail, [0.5, 0.5], 'webp');
    }

    /**
     * @ParamProviders("originals")
     *
     * @Revs(2)
     *
     * @Iterations(3)
     */
    public function benchVips(): void
    {
        $this->vips?->render($this->image, $this->thumbnail, [0.5, 0.5], 'webp');
    }

    /**
     * @return Generator<string, array{width: int<1, max>, height: int<1, max>}>
     */
    public function originals(): Generator
    {
        yield '1920×1080' => ['width' => 1_920, 'height' => 1_080];
        yield '6000×4000' => ['width' => 6_000, 'height' => 4_000];
    }
}
