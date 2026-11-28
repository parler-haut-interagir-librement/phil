<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

/**
 * What a viewer without the right sees of a content: a locked entry that says what remains to be done, or nothing at
 * all, the content answered as not found.
 */
enum WhenDenied: string
{
    case Locked = 'locked';
    case Hidden = 'hidden';
}
