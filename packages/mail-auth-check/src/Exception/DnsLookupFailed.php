<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Exception;

use RuntimeException;

/**
 * The resolver could not answer: a timeout, a refused query, a server failure. A name without records is not a
 * failure: the resolver answers it with an empty list.
 */
final class DnsLookupFailed extends RuntimeException implements MailAuthCheckException
{
    public static function for(string $name): self
    {
        return new self(\sprintf('The TXT records of %s could not be read.', $name));
    }
}
