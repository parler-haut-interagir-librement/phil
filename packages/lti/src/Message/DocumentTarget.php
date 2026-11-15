<?php

declare(strict_types=1);

namespace Phil\Lti\Message;

/**
 * Where the platform shows the tool.
 */
enum DocumentTarget: string
{
    case Iframe = 'iframe';
    case Window = 'window';
    case Embed = 'embed';
}
