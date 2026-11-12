<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Image;

use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * Chooses the image processor the configuration names: `vips`, which must be found, or `auto`, libvips when it is
 * found and GD otherwise.
 */
final class ImageProcessors
{
    public const array CHOICES = ['gd', 'vips', 'auto'];

    /**
     * @param string|null $vips      the path of `vips`; searched on the `PATH` when null
     * @param int<1, max> $maxPixels
     * @param string|null $path      the directories searched, the process's `PATH` by default
     *
     * @throws InvalidMedia when `vips` is required and not found
     */
    public static function choose(string $choice, ?string $vips, int $maxPixels, ImageProcessor $gd, ?string $path = null): ImageProcessor
    {
        if ('gd' === $choice) {
            return $gd;
        }

        $processor = null === $vips ? VipsImageProcessor::find($maxPixels, $path) : VipsImageProcessor::of($vips, $maxPixels);

        if ('vips' === $choice && !$processor instanceof VipsImageProcessor) {
            throw InvalidMedia::because('The image processor is vips, and no vips was found on the PATH: install libvips, or name its path in phil_media.vips_binary.');
        }

        return $processor ?? $gd;
    }
}
