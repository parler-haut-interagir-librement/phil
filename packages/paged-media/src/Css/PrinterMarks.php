<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Css;

/**
 * The marks a paged-media engine draws outside the page, in its bleed: where to cut, and where to align the plates.
 */
enum PrinterMarks: string
{
    case None = 'none';
    case Crop = 'crop';
    case Cross = 'cross';
    case CropAndCross = 'crop cross';
}
