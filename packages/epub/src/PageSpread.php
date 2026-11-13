<?php

declare(strict_types=1);

namespace Phil\Epub;

/**
 * The side of a two-page spread a fixed-layout document takes: the left page, the right page, or both, centred.
 */
enum PageSpread: string
{
    case Left = 'page-spread-left';
    case Right = 'page-spread-right';
    case Center = 'rendition:page-spread-center';
}
