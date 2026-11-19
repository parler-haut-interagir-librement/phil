<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application\Contract;

use Phil\MapBundle\Domain\Exception\TilingFailed;
use Phil\MapBundle\Domain\ValueObject\TilePyramid;

/**
 * Cuts a large image into the tiles of a floor plan: `<directory>/<z>/<x>/<y>.png`, from zoom 0 to the zoom at which a
 * tile shows the image's own pixels, as `FloorPlan` places them. The bundle's adapter uses PHP's GD extension.
 *
 * Invariants: the image's size is read, and checked against the limit, before its pixels are; the tiles of the last
 * zoom are the image's pixels, unscaled; nothing is written outside the directory.
 */
interface ImageTiler
{
    /**
     * @throws TilingFailed when the image cannot be read or is too large, or a tile cannot be written
     */
    public function tile(string $image, string $directory): TilePyramid;
}
