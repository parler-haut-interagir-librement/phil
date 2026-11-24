<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Exception;

use DomainException;
use Phil\Money\Money;

/**
 * An operation the ledger refuses before anything moves: more units than its owners hold, a return of units never sold.
 */
final class ConsignmentRefused extends DomainException
{
    public static function shortage(string $sku, string $location, int $wanted, int $held): self
    {
        return new self(\sprintf('The SKU %s holds %d unit(s) at %s in the ledger; %d were asked.', $sku, $held, $location, $wanted));
    }

    public static function notSold(string $reference, string $sku, int $wanted, int $returnable): self
    {
        return new self(\sprintf('The sale %s left %d unit(s) of %s that are not returned yet; %d were brought back.', $reference, $returnable, $sku, $wanted));
    }

    public static function sameLocation(string $location): self
    {
        return new self(\sprintf('A transfer goes from one location to another, not from %s to itself.', $location));
    }

    public static function noLayerFor(string $sku, string $owner): self
    {
        return new self(\sprintf('A count found more units of %s than %s holds, and %s never received it: receive the units instead, with their cost or their terms.', $sku, $owner, $owner));
    }

    public static function beyondOnHand(string $sku, string $location, int $layered, int $onHand): self
    {
        return new self(\sprintf('The ledger would hold %d unit(s) of %s at %s, and the stock has %d on hand.', $layered, $sku, $location, $onHand));
    }

    public static function purchaseOrderElsewhere(string $number, string $location, string $receivedAt): self
    {
        return new self(\sprintf('The purchase order %s is received at %s, not at %s.', $number, $location, $receivedAt));
    }

    public static function purchaseOrderOwner(string $number, string $sku, string $owner, string $received): self
    {
        return new self(\sprintf('The purchase order %s orders %s from the consignor %s; the receipt gives it to %s.', $number, $sku, $owner, $received));
    }

    public static function purchaseOrderCost(string $number, string $sku, Money $ordered, Money $received): self
    {
        return new self(\sprintf('The purchase order %s buys %s at %s %s a unit; the receipt says %s %s.', $number, $sku, $ordered->toDecimal(), $ordered->currency->code, $received->toDecimal(), $received->currency->code));
    }

    public static function notOrdered(string $orderNumber, string $sku, int $shipped, int $ordered): self
    {
        return new self(\sprintf('The order %s shipped %d unit(s) of %s and holds %d.', $orderNumber, $shipped, $sku, $ordered));
    }

    public static function notConsigned(string $what): self
    {
        return new self(\sprintf("%s concerns a consignor's units, not the shop's.", $what));
    }

    public static function noBuyOutCost(string $sku, string $consignor): self
    {
        return new self(\sprintf('The units of %s that %s left on a commission have no agreed cost: give the unit cost of the buy-out.', $sku, $consignor));
    }

    public static function agreementInThePast(string $consignor, string $effectiveFrom, string $since): self
    {
        return new self(\sprintf('A new agreement with %s cannot apply from %s: it starts at %s or later, so that it never changes the past.', $consignor, $effectiveFrom, $since));
    }

    public static function periodNotOver(string $end, string $at): self
    {
        return new self(\sprintf('A statement covers a period that is over; the period ends at %s, after %s.', $end, $at));
    }

    public static function tooManyLines(string $consignor, int $limit): self
    {
        return new self(\sprintf('The ledger holds more than %d entries for %s over the period: issue statements of shorter periods.', $limit, $consignor));
    }

    public static function duplicatePayout(string $key): self
    {
        return new self(\sprintf('A payout with the key %s is written already: the same payout is never written twice.', $key));
    }

    public static function alreadyReversed(string $id): self
    {
        return new self(\sprintf('The payout %s is reversed already.', $id));
    }

    public static function unknownPayout(string $id): self
    {
        return new self(\sprintf('No payout %s is written.', $id));
    }

    public static function beyondBalance(string $consignor, string $amount, string $balance): self
    {
        return new self(\sprintf('A payout of %s to %s is more than the %s the shop owes them.', $amount, $consignor, $balance));
    }
}
