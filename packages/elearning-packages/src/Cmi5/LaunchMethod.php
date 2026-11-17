<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Cmi5;

/**
 * How a cmi5 assignable unit asks to be launched: in any window, framed in the page included, or in a window of its
 * own.
 */
enum LaunchMethod: string
{
    case AnyWindow = 'AnyWindow';
    case OwnWindow = 'OwnWindow';
}
