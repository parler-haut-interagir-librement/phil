<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Domain\Exception;

use RuntimeException;

/**
 * A signed request or response refused: a header missing, a date out of the window, a nonce seen before, another
 * site, or a signature that the key does not verify. The reason is for the logs, never for the caller.
 */
final class SignatureRefused extends RuntimeException implements SitesConsoleException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
