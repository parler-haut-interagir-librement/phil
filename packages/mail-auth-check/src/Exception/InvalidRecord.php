<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Exception;

use RuntimeException;

/**
 * A published SPF, DKIM or DMARC record that cannot be read: its message says why, in words a domain owner can act on.
 */
final class InvalidRecord extends RuntimeException implements MailAuthCheckException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
