<?php

declare(strict_types=1);

namespace Phil\SvgChart\Exception;

use InvalidArgumentException;
use Throwable;

/**
 * A rasterizer that cannot be built as asked: a limit out of its range, no font, or a font file that cannot be read
 * or is not TrueType or OpenType.
 */
final class InvalidRasterizer extends InvalidArgumentException implements SvgChartException
{
    public static function because(string $problem, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The rasterizer cannot be built: %s.', $problem), previous: $previous);
    }
}
