<?php

declare(strict_types=1);

namespace Phil\EmailPreview;

/**
 * How much a finding matters: an error is fixed before the email is sent, a warning asks for a look.
 */
enum Severity: string
{
    /** The email breaks a rule of delivery or of reading: fix it before it is sent. */
    case Error = 'error';

    /** The email may read or deliver worse than it could: someone looks before it is sent. */
    case Warning = 'warning';
}
