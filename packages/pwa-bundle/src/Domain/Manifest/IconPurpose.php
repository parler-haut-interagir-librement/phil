<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\Manifest;

/**
 * What an icon is drawn for: as it is, cropped to a mask, or as a single colour.
 */
enum IconPurpose: string
{
    case Any = 'any';
    case Maskable = 'maskable';
    case Monochrome = 'monochrome';
}
