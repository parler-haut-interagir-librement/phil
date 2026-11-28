<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\Exception;

use RuntimeException;

/**
 * A rendered receipt refused before it is sent: it has no plain-text part, loads a remote image, carries a link
 * tagged for tracking, or asks for something no receipt does.
 */
final class NotTransactional extends RuntimeException implements ReceiptEmailException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
