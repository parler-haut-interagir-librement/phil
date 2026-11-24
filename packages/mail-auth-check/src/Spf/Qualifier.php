<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Spf;

/**
 * The result an SPF mechanism gives when it matches (RFC 7208, section 4.6.2).
 */
enum Qualifier: string
{
    case Pass = '+';
    case Fail = '-';
    case SoftFail = '~';
    case Neutral = '?';
}
