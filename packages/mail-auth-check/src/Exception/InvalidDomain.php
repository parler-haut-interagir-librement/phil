<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Exception;

use InvalidArgumentException;

/**
 * A domain name or a DKIM selector of a sender profile that is not a valid host name.
 */
final class InvalidDomain extends InvalidArgumentException implements MailAuthCheckException
{
    public static function because(string $name, string $reason): self
    {
        return new self(\sprintf('"%s" is not a valid domain name: %s.', self::printable($name), $reason));
    }

    public static function needsIntl(string $name): self
    {
        return new self(\sprintf(
            '"%s" is an internationalised domain name: install ext-intl to convert it, or give its ASCII form (xn--).',
            self::printable($name),
        ));
    }

    private static function printable(string $name): string
    {
        return (string) preg_replace('/[^\x20-\x7E]/', '?', substr($name, 0, 80));
    }
}
