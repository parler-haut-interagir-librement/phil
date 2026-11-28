<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

/**
 * Who may read a content in full: everyone, every signed-in member, or whoever a rule lets in.
 */
enum Audience: string
{
    case Public = 'public';
    case Members = 'members';
    case Restricted = 'restricted';
}
