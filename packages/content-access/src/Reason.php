<?php

declare(strict_types=1);

namespace Phil\ContentAccess;

/**
 * Why a decision was taken, as a stable code an application may translate.
 */
enum Reason: string
{
    /** The content is public. */
    case Public = 'public';
    /** The early access ended: the content is public for good. */
    case EarlyAccessEnded = 'early-access-ended';
    /** The content is for members, and the viewer is one. */
    case Member = 'member';
    /** The content is restricted, and its rule holds for the viewer. */
    case RulePassed = 'rule-passed';
    /** The content is for members, and the viewer is not signed in. */
    case Anonymous = 'anonymous';
    /** The content is restricted, and its rule does not hold for the viewer. */
    case RuleFailed = 'rule-failed';
}
