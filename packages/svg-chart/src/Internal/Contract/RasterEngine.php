<?php

declare(strict_types=1);

namespace Phil\SvgChart\Internal\Contract;

use Phil\SvgChart\Exception\RasterizationFailed;

/**
 * What `ResvgRasterizer` asks of the renderer: the size of an image, then its PNG.
 */
interface RasterEngine
{
    /**
     * The size in pixels of the PNG `render()` would draw at that scale, without drawing it.
     *
     * @return array{width: int, height: int}
     *
     * @throws RasterizationFailed when the renderer refuses the image
     */
    public function measure(string $svg, int $scale): array;

    /**
     * @throws RasterizationFailed when the renderer refuses the image
     */
    public function render(string $svg, int $scale): string;
}
