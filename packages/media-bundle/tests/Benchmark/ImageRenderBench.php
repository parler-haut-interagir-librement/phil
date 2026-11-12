<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Benchmark;

use Generator;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\RenderBox;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Phil\MediaBundle\Infrastructure\Image\GdImageProcessor;
use Phil\MediaBundle\Tests\Unit\Fixtures\Images;

/**
 * The first request for a format renders it: GD decodes the whole original, resamples it and encodes it, which is the
 * slowest thing a page with new images waits for, AVIF above all. An image beyond the pixel limit must be refused from
 * its header alone, before any decoding.
 *
 * `mem_peak` counts PHP's own memory only: when GD is a system library, its pixel buffers are not in it.
 *
 * @BeforeMethods("setUp")
 *
 * @Groups({"media"})
 */
final class ImageRenderBench
{
    private readonly GdImageProcessor $processor;

    private readonly ImageFormat $thumbnail;

    private string $image = '';

    private string $bomb = '';

    public function __construct()
    {
        $this->processor = new GdImageProcessor();
        $this->thumbnail = ImageFormat::of('thumbnail', 400, 300);
    }

    /**
     * A JPEG photo-like original, gradients and noise so that the encoders have work to do, as uploads are.
     *
     * @param array{width?: int<1, max>, height?: int<1, max>, type?: string} $params
     */
    public function setUp(array $params): void
    {
        $this->bomb = $this->pngHeader(20_000, 20_000);

        if (!isset($params['width'], $params['height'])) {
            return;
        }

        $width = $params['width'];
        $height = $params['height'];
        $canvas = Images::canvas($width, $height);

        for ($y = 0; $y < $height; $y += 4) {
            for ($x = 0; $x < $width; $x += 4) {
                $color = (int) imagecolorallocate($canvas, max(0, min(255, intdiv(255 * $x, $width))), max(0, min(255, intdiv(255 * $y, $height))), random_int(0, 255));
                imagefilledrectangle($canvas, $x, $y, $x + 3, $y + 3, $color);
            }
        }

        ob_start();
        imagejpeg($canvas, null, 85);
        $this->image = (string) ob_get_clean();
    }

    /**
     * @ParamProviders({"originals", "types"})
     *
     * @Revs(3)
     *
     * @Iterations(3)
     *
     * @param array{type: string} $params
     */
    public function benchRender(array $params): void
    {
        $this->processor->render($this->image, $this->thumbnail, [0.3, 0.6], $params['type']);
    }

    /**
     * A 400 × 400 PNG header claiming 20 000 × 20 000 pixels: refused from the header, never decoded.
     */
    public function benchDecompressionBombRefused(): void
    {
        try {
            $this->processor->render($this->bomb, $this->thumbnail, [0.5, 0.5], 'webp');
        } catch (InvalidMedia) {
        }
    }

    /**
     * The crop geometry alone, computed for every format of every rendered image.
     */
    public function benchRenderBox(): void
    {
        RenderBox::fill(4000, 3000, 400, 300, 'crop', [0.3, 0.6]);
    }

    /**
     * @return Generator<string, array{width: int<1, max>, height: int<1, max>}>
     */
    public function originals(): Generator
    {
        yield '640×480' => ['width' => 640, 'height' => 480];
        yield '1920×1080' => ['width' => 1_920, 'height' => 1_080];
        yield '4000×3000' => ['width' => 4_000, 'height' => 3_000];
    }

    /**
     * @return Generator<string, array{type: string}>
     */
    public function types(): Generator
    {
        yield 'webp' => ['type' => 'webp'];
        yield 'avif' => ['type' => 'avif'];
        yield 'jpeg' => ['type' => 'jpeg'];
        yield 'png' => ['type' => 'png'];
    }

    /**
     * The signature and header chunk of a PNG, which is all `getimagesizefromstring()` reads.
     */
    private function pngHeader(int $width, int $height): string
    {
        $header = 'IHDR'.pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

        return "\x89PNG\r\n\x1A\n".pack('N', 13).$header.pack('N', crc32($header));
    }
}
