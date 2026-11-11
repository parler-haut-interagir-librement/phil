<?php

declare(strict_types=1);

namespace Phil\Fonts;

/**
 * The slope of a face; each value is a keyword of the CSS `font-style` descriptor.
 */
enum FontStyle: string
{
    case Normal = 'normal';
    case Italic = 'italic';
    case Oblique = 'oblique';
}
