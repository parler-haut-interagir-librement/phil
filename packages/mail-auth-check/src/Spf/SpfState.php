<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Spf;

/**
 * What the TXT records of a domain hold as SPF.
 */
enum SpfState: string
{
    case Found = 'found';
    case Missing = 'missing';
    case Multiple = 'multiple';
    case Invalid = 'invalid';
    case LookupFailed = 'lookup-failed';
}
