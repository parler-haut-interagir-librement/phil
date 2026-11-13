<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\LibreOffice;

/**
 * How a viewer fits the page.
 */
enum Magnification: string
{
    case Default = 'default';
    case FitWindow = 'fitWindow';
    case FitWidth = 'fitWidth';
    case FitVisible = 'fitVisible';
    case Zoom = 'zoom';
}
