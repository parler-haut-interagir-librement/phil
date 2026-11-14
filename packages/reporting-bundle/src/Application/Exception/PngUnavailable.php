<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Exception;

use Phil\ReportingBundle\Domain\Exception\ReportingException;
use RuntimeException;

/**
 * A PNG asked of a chart where none is drawn: without the resvg extension, or without the fonts of
 * `phil_reporting.png.fonts`, the charts are SVG images only.
 */
final class PngUnavailable extends RuntimeException implements ReportingException
{
    public static function create(): self
    {
        return new self('The charts are drawn as SVG only: a PNG needs the resvg extension and the fonts of "phil_reporting.png.fonts".');
    }
}
