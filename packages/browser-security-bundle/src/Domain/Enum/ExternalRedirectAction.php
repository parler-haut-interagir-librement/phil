<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Enum;

/**
 * What becomes of a response that would send the browser to a host not allowed.
 */
enum ExternalRedirectAction: string
{
    case Refuse = 'refuse';
    case Fallback = 'fallback';
    case Log = 'log';
}
