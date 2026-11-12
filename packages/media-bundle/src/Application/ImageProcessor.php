<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

/**
 * Renders an image to a format, in a file type: `webp`, `avif`, `jpeg`, `png`.
 */
interface ImageProcessor
{
    /**
     * @param array{float, float} $focalPoint from 0 to 1 across and down
     */
    public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string;
}
