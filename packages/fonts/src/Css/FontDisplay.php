<?php

declare(strict_types=1);

namespace Phil\Fonts\Css;

/**
 * How a browser shows text while the font loads; each value is a keyword of the `font-display` descriptor.
 */
enum FontDisplay: string
{
    case Auto = 'auto';
    case Block = 'block';
    case Swap = 'swap';
    case Fallback = 'fallback';
    case Optional = 'optional';
}
