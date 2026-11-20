<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A message or an order the syndication refuses.
 */
final class SyndicationRefused extends DomainException implements DomainError
{
    public static function role(string $code, string $expected): self
    {
        return new self(\sprintf('The partner %s is not a %s.', $code, $expected));
    }

    public static function unreadable(string $type, string $reason): self
    {
        return new self(\sprintf('The "%s" message cannot be read: %s.', $type, $reason));
    }

    public static function unknownProduct(string $supplier, string $sku): self
    {
        return new self(\sprintf('The supplier %s feeds no product %s.', $supplier, $sku));
    }

    public static function notFed(string $reseller, string $sku): self
    {
        return new self(\sprintf('The reseller %s is not fed the product %s.', $reseller, $sku));
    }

    public static function notPlaced(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: only a placed order is forwarded.', $orderNumber));
    }

    public static function costNotPublished(string $reseller, string $sku, string $cost, int $graceDays): self
    {
        return new self(\sprintf('The reseller %s owes %s for %s: no price published to it in the last %d days costs that.', $reseller, $cost, $sku, $graceDays));
    }

    public static function unknownOrder(string $partner, string $orderNumber): self
    {
        return new self(\sprintf('No order %s is in the ledger with %s.', $orderNumber, $partner));
    }

    public static function notInOrder(string $orderNumber, string $sku): self
    {
        return new self(\sprintf('The order %s holds no %s.', $orderNumber, $sku));
    }

    public static function overReturned(string $reference, string $sku, int $asked, int $left): self
    {
        return new self(\sprintf('%d of %s cannot be returned against %s: %d are left.', $asked, $sku, $reference, $left));
    }

    public static function unknownReturn(string $partner, string $returnNumber): self
    {
        return new self(\sprintf('No return %s is known with %s.', $returnNumber, $partner));
    }

    public static function returnReceived(string $returnNumber): self
    {
        return new self(\sprintf('The return %s is already received.', $returnNumber));
    }

    public static function creditMismatch(string $returnNumber, string $claimed, string $computed): self
    {
        return new self(\sprintf('The return %s is credited %s by the supplier and %s by the ledger here.', $returnNumber, $claimed, $computed));
    }

    public static function returnKnown(string $returnNumber): self
    {
        return new self(\sprintf('The return %s is already requested.', $returnNumber));
    }
}
