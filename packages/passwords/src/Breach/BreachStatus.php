<?php

declare(strict_types=1);

namespace Phil\Passwords\Breach;

/**
 * The outcome of a breach check.
 */
enum BreachStatus: string
{
    /** The password appears in known breaches at least as often as the threshold. */
    case Compromised = 'compromised';

    /** The password appears in no known breach, or less often than the threshold. */
    case NotFound = 'not_found';

    /** The source could not answer: whether to accept the password is the application's decision. */
    case Unavailable = 'unavailable';
}
