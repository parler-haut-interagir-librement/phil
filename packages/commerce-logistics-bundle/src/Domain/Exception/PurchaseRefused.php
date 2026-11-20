<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A purchase order, or a receipt against it, that cannot happen as asked.
 */
final class PurchaseRefused extends DomainException implements DomainError
{
    public static function unknown(string $number): self
    {
        return new self(\sprintf('No purchase order is known as "%s".', $number));
    }

    public static function duplicate(string $number): self
    {
        return new self(\sprintf('The purchase order %s already exists.', $number));
    }

    public static function notOpen(string $number): self
    {
        return new self(\sprintf('The purchase order %s is no longer open.', $number));
    }

    public static function notOrdered(string $number, string $sku): self
    {
        return new self(\sprintf('The purchase order %s does not order %s.', $number, $sku));
    }

    public static function overReceived(string $number, string $sku, int $asked, int $expected): self
    {
        return new self(\sprintf('%d of %s cannot be received against %s: %d are still expected.', $asked, $sku, $number, $expected));
    }
}
