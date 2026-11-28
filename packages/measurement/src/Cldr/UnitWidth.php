<?php

declare(strict_types=1);

namespace Phil\Measurement\Cldr;

/**
 * How long a unit is written, as CLDR names its widths: `12 kilograms`, `12 kg`, `12kg`.
 */
enum UnitWidth: string
{
    case Long = 'long';
    case Short = 'short';
    case Narrow = 'narrow';
}
