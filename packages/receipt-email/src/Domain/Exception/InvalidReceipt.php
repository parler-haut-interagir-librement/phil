<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\Exception;

use InvalidArgumentException;

/**
 * A receipt, a donation or one of their parts refused as given: the message says which rule it breaks, never the
 * personal data it holds.
 */
final class InvalidReceipt extends InvalidArgumentException implements ReceiptEmailException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
