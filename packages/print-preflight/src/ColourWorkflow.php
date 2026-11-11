<?php

declare(strict_types=1);

namespace Phil\PrintPreflight;

/**
 * The colours a printer expects its files in.
 */
enum ColourWorkflow: string
{
    /** Offset and most digital presses: colours separated into cyan, magenta, yellow and black. */
    case Cmyk = 'cmyk';

    /** Print on demand services that convert RGB files themselves, with their own profile. */
    case Rgb = 'rgb';
}
