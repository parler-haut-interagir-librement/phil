<?php

declare(strict_types=1);

namespace Phil\SvgChart\Contract;

use Phil\SvgChart\Exception\RasterizationFailed;

/**
 * Draws an SVG image as a PNG, for where SVG is not read: an e-mail, an image to share, a document that takes bitmaps
 * only.
 */
interface Rasterizer
{
    /**
     * The PNG of the image, at its own size times the scale, from 1 to 4: 2 for screens of high density.
     *
     * @throws RasterizationFailed when the image is not drawn; nothing is drawn in part
     */
    public function png(string $svg, int $scale = 1): string;
}
