<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Imap\Exception;

use Phil\NewsletterBundle\Domain\Exception\NewsletterException;
use RuntimeException;

/**
 * The IMAP server could not be reached, refused a command, or answered outside the protocol or the limits.
 */
final class ImapFailed extends RuntimeException implements NewsletterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
