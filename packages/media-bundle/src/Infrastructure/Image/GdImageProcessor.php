<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Image;

use GdImage;
use Override;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\Transform\Geometry;
use Phil\MediaBundle\Application\Transform\Step;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * Renders images with GD through the transformations of a format, transparency kept, as WebP, AVIF, JPEG or PNG;
 * the quality of the format is that of the three lossy types. With a `BinaryEncoder` for WebP or AVIF, GD draws a
 * lossless PNG that the encoder compresses.
 *
 * An image larger than the pixel limit is refused before GD decodes it: a few hundred kilobytes of PNG can hold an
 * image whose decoding takes gigabytes.
 */
final readonly class GdImageProcessor implements ImageProcessor
{
    public const int MAX_PIXELS = 25_000_000;

    /**
     * @param int<1, max>                   $maxPixels the largest width times height decoded
     * @param array<string, ?BinaryEncoder> $encoders  by file type, `webp` or `avif`; `null`, GD
     */
    public function __construct(
        private int $maxPixels = self::MAX_PIXELS,
        private array $encoders = [],
    ) {
    }

    #[Override]
    public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string
    {
        if (!\in_array($type, ['webp', 'avif', 'jpeg', 'png'], true)) {
            throw InvalidMedia::because(\sprintf('An image is rendered as webp, avif, jpeg or png; not as "%s".', $type));
        }

        $size = @getimagesizefromstring($image);

        if (false === $size) {
            throw InvalidMedia::because('The file cannot be read as an image.');
        }

        if ($size[0] * $size[1] > $this->maxPixels) {
            throw InvalidMedia::because(\sprintf('An image of %d × %d pixels is larger than the %d pixels rendered at most.', $size[0], $size[1], $this->maxPixels));
        }

        $source = @imagecreatefromstring($image);

        if (!$source instanceof GdImage) {
            throw InvalidMedia::because('The file cannot be read as an image.');
        }

        // An image no transformation redraws is encoded as decoded, transparency included.
        imagesavealpha($source, true);
        $width = max(1, imagesx($source));
        $height = max(1, imagesy($source));
        $image = Geometry::of($width, $height, $focalPoint[0], $focalPoint[1]);

        foreach ($format->transformers as $transformer) {
            $step = $transformer->step($image);

            if ($step->changes($image)) {
                $source = $this->draw($source, $step);
                $image = $step->next($image);
            }
        }

        $encoder = $this->encoders[$type] ?? null;

        if (null !== $encoder) {
            return $encoder->encode($this->encoded($source, 'png', $format->quality), $format->quality);
        }

        return $this->encoded($source, $type, $format->quality);
    }

    private function draw(GdImage $source, Step $step): GdImage
    {
        $target = $this->canvas($step->canvasWidth, $step->canvasHeight);
        // A true-colour pixel is 0xAARRGGBB, its alpha from 0, opaque, to 127, transparent.
        imagefilledrectangle($target, 0, 0, $step->canvasWidth - 1, $step->canvasHeight - 1, $step->background ?? 0x7F000000);
        imagecopyresampled($target, $source, $step->targetLeft, $step->targetTop, $step->sourceLeft, $step->sourceTop, $step->targetWidth, $step->targetHeight, $step->sourceWidth, $step->sourceHeight);

        return $target;
    }

    private function encoded(GdImage $image, string $type, int $quality): string
    {
        ob_start();

        match ($type) {
            'webp' => imagewebp($image, null, $quality),
            'avif' => imageavif($image, null, $quality),
            'jpeg' => imagejpeg($image, null, $quality),
            default => imagepng($image),
        };

        return (string) ob_get_clean();
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);

        if (!$canvas instanceof GdImage) {
            throw InvalidMedia::because('There is not enough memory to render this image.');
        }

        // The original's pixels are copied as they are, transparency included.
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        return $canvas;
    }
}
