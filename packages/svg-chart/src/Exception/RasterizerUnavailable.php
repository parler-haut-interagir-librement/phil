<?php

declare(strict_types=1);

namespace Phil\SvgChart\Exception;

use RuntimeException;

/**
 * A rasterizer whose PHP extension is not loaded: the PNG of a chart is not offered, and its SVG is all there is.
 */
final class RasterizerUnavailable extends RuntimeException implements SvgChartException
{
    public static function extension(string $extension): self
    {
        return new self(\sprintf('The PHP extension "%s" is not loaded: charts are drawn as SVG only.', $extension));
    }
}
