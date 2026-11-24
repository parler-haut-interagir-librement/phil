<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck;

/**
 * How closely a DMARC record asks the SPF or DKIM domain to match the sending domain: relaxed accepts any domain of
 * the same organisational domain, strict only the sending domain itself.
 */
enum Alignment: string
{
    case Relaxed = 'r';
    case Strict = 's';
}
