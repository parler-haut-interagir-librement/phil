<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Exception;

use InvalidArgumentException;
use Phil\EmailAddress\SyntaxError;

/**
 * A text that is not an email address. The text itself stays out of the message, which may reach a log: it is
 * personal data.
 */
final class InvalidEmailAddress extends InvalidArgumentException implements EmailAddressException
{
    public function __construct(
        public readonly SyntaxError $reason,
    ) {
        parent::__construct('Invalid email address: '.$reason->describe().'.');
    }
}
