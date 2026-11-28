<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

/**
 * What a decision lets a viewer see: the whole content, its public part before the cut, a locked entry, or nothing.
 */
enum Access: string
{
    case Full = 'full';
    case Preview = 'preview';
    case Locked = 'locked';
    case Hidden = 'hidden';
}
