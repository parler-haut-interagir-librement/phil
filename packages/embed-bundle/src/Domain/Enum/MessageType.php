<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Domain\Enum;

/**
 * The messages of the frame protocol: `ready` and `resize` go from the frame to the page that holds it, `theme` from
 * the page to the frame.
 */
enum MessageType: string
{
    case Ready = 'ready';
    case Resize = 'resize';
    case Theme = 'theme';
}
