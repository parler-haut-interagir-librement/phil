<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Domain\Enum;

/**
 * Whether the browser blocked what a report describes, under `Content-Security-Policy`, or only reported it, under
 * `Content-Security-Policy-Report-Only`.
 */
enum Disposition: string
{
    case Enforce = 'enforce';
    case Report = 'report';
}
