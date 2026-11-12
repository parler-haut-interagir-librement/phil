<?php

declare(strict_types=1);

namespace Phil\EmailAddress\Exception;

use InvalidArgumentException;
use Phil\EmailAddress\SyntaxError;

/**
 * A list of domains holding an entry that is not a domain name, a release that cannot be recorded, or a data
 * directory that cannot be written.
 */
final class InvalidDomainList extends InvalidArgumentException implements EmailAddressException
{
    public static function entry(int $line, SyntaxError $reason): self
    {
        return new self(\sprintf('Line %d of the domain list is not a domain: %s.', $line, $reason->describe()));
    }

    public static function domain(string $domain, SyntaxError $reason): self
    {
        return new self(\sprintf('"%s" is not a domain: %s.', $domain, $reason->describe()));
    }

    public static function notAWord(string $value): self
    {
        return new self(\sprintf('"%s" must be printable ASCII without spaces, to fit on its line of VERSION.', addcslashes($value, "\0..\37\177..\377")));
    }

    public static function noRelease(): self
    {
        return new self('The list does not start with the "# Version" line that names its release.');
    }

    public static function unwritable(string $path): self
    {
        return new self(\sprintf('The file "%s" cannot be written.', $path));
    }
}
