<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\LibreOffice;

/**
 * The side panel a viewer opens.
 */
enum InitialView: string
{
    case Default = 'default';
    case Outline = 'outline';
    case Thumbnails = 'thumbnails';
}
