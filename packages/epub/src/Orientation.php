<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * The orientation the pages of a fixed-layout book are meant to be read in: either, landscape or portrait.
 */
enum Orientation: string
{
    case Auto = 'auto';
    case Landscape = 'landscape';
    case Portrait = 'portrait';
}
