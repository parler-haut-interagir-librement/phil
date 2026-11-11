<?php

declare(strict_types=1);

namespace Phil\BounceParser\Rule;

/**
 * The text of a message a rule looks at.
 */
enum Field: string
{
    /** The subject, its encoded words decoded. */
    case Subject = 'subject';
    /** The `From` field as written. */
    case From = 'from';
    /** The text parts of the message, not those of the message it returns, up to the limit of text. */
    case Body = 'body';
    /** The diagnostics of the recipients of a delivery status notification, one a line. */
    case Diagnostic = 'diagnostic';
    /** Every value of one header field, one a line: the rule names the field. */
    case Header = 'header';
}
