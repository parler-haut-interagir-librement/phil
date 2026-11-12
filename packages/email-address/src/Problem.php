<?php

declare(strict_types=1);

namespace Phil\EmailAddress;

/**
 * Why an address is refused, beyond its syntax.
 */
enum Problem: string
{
    /** The text is not an address: `Validation::$syntaxError` says why. */
    case Syntax = 'syntax';
    /** Its domain ends with a top-level domain the root zone does not delegate. */
    case UnknownTopLevelDomain = 'unknown_top_level_domain';
    /** Its domain is one the application refuses. */
    case DeniedDomain = 'denied_domain';
    /** The application accepts only some domains, and not this one. */
    case DomainNotAllowed = 'domain_not_allowed';
    /** Its domain gives disposable mailboxes. */
    case Disposable = 'disposable';
    /** No server receives the mail of its domain. */
    case NoMailServer = 'no_mail_server';
    /** Its domain says, by a null MX record, that it receives no mail. */
    case RefusesMail = 'refuses_mail';
}
