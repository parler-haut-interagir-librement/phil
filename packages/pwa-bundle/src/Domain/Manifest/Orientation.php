<?php

declare(strict_types=1);

namespace Phil\PwaBundle\Domain\Manifest;

/**
 * The orientation the installed site asks the screen for.
 */
enum Orientation: string
{
    case Any = 'any';
    case Natural = 'natural';
    case Landscape = 'landscape';
    case LandscapePrimary = 'landscape-primary';
    case LandscapeSecondary = 'landscape-secondary';
    case Portrait = 'portrait';
    case PortraitPrimary = 'portrait-primary';
    case PortraitSecondary = 'portrait-secondary';
}
