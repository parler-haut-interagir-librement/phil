<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A receipt or a credit note that cannot be issued as asked.
 */
final class ReceiptRefused extends DomainException implements DomainError
{
    public static function orderNotPlaced(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: only a placed order gets a receipt.', $orderNumber));
    }

    public static function alreadyIssued(string $orderNumber, string $receiptNumber): self
    {
        return new self(\sprintf('The order %s already has the receipt %s.', $orderNumber, $receiptNumber));
    }

    public static function notAReceipt(string $number): self
    {
        return new self(\sprintf('%s is a credit note: only a receipt is credited.', $number));
    }

    public static function lineNotCreditable(string $receiptNumber, string $line): self
    {
        return new self(\sprintf('The receipt %s has no line %s left to credit.', $receiptNumber, $line));
    }

    public static function nothingToCredit(string $receiptNumber): self
    {
        return new self(\sprintf('The receipt %s has nothing left to credit.', $receiptNumber));
    }
}
