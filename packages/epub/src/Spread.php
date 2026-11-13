<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * When the reading system shows two pages of a fixed-layout book side by side: as it chooses, never, in landscape
 * orientation only, or always.
 */
enum Spread: string
{
    case Auto = 'auto';
    case None = 'none';
    case Landscape = 'landscape';
    case Both = 'both';
}
