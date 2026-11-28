<?php

declare(strict_types=1);

namespace Phil\ReceiptEmail\Domain\Exception;

use RuntimeException;

/**
 * A receipt, or its recipient, that the stores no longer know: the worker read it again and found nothing.
 */
final class ReceiptNotFound extends RuntimeException implements ReceiptEmailException
{
    public static function payment(string $number): self
    {
        return new self(\sprintf('No payment receipt %s is found.', $number));
    }

    public static function donation(string $serialNumber): self
    {
        return new self(\sprintf('No donation receipt %s is found.', $serialNumber));
    }

    public static function recipient(string $number): self
    {
        return new self(\sprintf('The receipt %s has no recipient to send it to.', $number));
    }
}
