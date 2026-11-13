<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

/**
 * The colour space every colour of a PDF is converted to.
 */
enum ColorTarget: string
{
    /** For a print shop. */
    case Cmyk = 'cmyk';
    /** For screens. */
    case Rgb = 'rgb';
    /** For black and white printing. */
    case Gray = 'gray';
    /** The output intent the PDF declares. */
    case OutputIntent = 'output-intent';
}
