<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck;

/**
 * The outcome of one finding. A failure blocks sending; a warning is worth fixing but does not.
 */
enum Status: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';
}
