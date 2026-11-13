<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

/**
 * The format pages are rendered in.
 */
enum ImageFormat: string
{
    case Png = 'png';
    case Jpeg = 'jpeg';
}
