<?php

declare(strict_types=1);

namespace Phil\SvgChart\Exception;

use RuntimeException;
use Throwable;

/**
 * An image not drawn as a PNG: larger than the limits allow in bytes or in pixels, at a scale out of range, refused by
 * the sanitizer, or refused by the renderer. Nothing is drawn: there is never a partial image.
 */
final class RasterizationFailed extends RuntimeException implements SvgChartException
{
    public static function because(string $problem, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The image cannot be drawn as a PNG: %s.', $problem), previous: $previous);
    }
}
