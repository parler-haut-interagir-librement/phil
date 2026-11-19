<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * A movement of stock that cannot happen as asked.
 */
final class StockRefused extends DomainException implements DomainError
{
    public static function notAvailable(string $sku, string $location, int $asked, int $available): self
    {
        return new self(\sprintf('%d of %s are asked at %s; %d are available.', $asked, $sku, $location, $available));
    }

    public static function quantity(int $quantity): self
    {
        return new self(\sprintf('A movement moves at least one unit; %d does not.', $quantity));
    }

    public static function notHeld(string $id): self
    {
        return new self(\sprintf('The reservation %s is no longer held.', $id));
    }

    public static function shortage(string $orderNumber, string $sku, int $missing): self
    {
        return new self(\sprintf('The order %s lacks %d of %s in stock.', $orderNumber, $missing, $sku));
    }

    public static function notReservable(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: nothing of it is reserved.', $orderNumber));
    }

    public static function unknownLocation(string $code): self
    {
        return new self(\sprintf('No location is known as "%s".', $code));
    }

    public static function sameLocation(string $code): self
    {
        return new self(\sprintf('A transfer goes from one location to another, not from %s to itself.', $code));
    }

    public static function notShippable(string $orderNumber): self
    {
        return new self(\sprintf('The order %s is not placed: nothing of it ships.', $orderNumber));
    }

    public static function notInOrder(string $orderNumber, string $line): self
    {
        return new self(\sprintf('The order %s has no line %s.', $orderNumber, $line));
    }

    public static function overShipped(string $line, int $asked, int $left): self
    {
        return new self(\sprintf('%d of %s cannot ship: %d are left to ship.', $asked, $line, $left));
    }

    public static function notOnHand(string $sku, string $location, int $asked, int $onHand): self
    {
        return new self(\sprintf('%d of %s cannot leave %s: %d are on hand.', $asked, $sku, $location, $onHand));
    }

    public static function serialNotInStock(string $sku, string $serial): self
    {
        return new self(\sprintf('The unit %s of %s is not in stock.', $serial, $sku));
    }

    public static function serialInStock(string $sku, string $serial): self
    {
        return new self(\sprintf('The unit %s of %s is already in stock.', $serial, $sku));
    }

    public static function serialUnknown(string $sku, string $serial): self
    {
        return new self(\sprintf('No unit %s of %s is known.', $serial, $sku));
    }

    public static function serialKnown(string $sku, string $serial): self
    {
        return new self(\sprintf('The unit %s of %s is already registered.', $serial, $sku));
    }

    public static function serialsBeyondStock(string $sku, string $location, int $serials, int $onHand): self
    {
        return new self(\sprintf('%d serialised units of %s at %s would exceed the %d on hand.', $serials, $sku, $location, $onHand));
    }

    public static function serialElsewhere(string $sku, string $serial, string $location): self
    {
        return new self(\sprintf('The unit %s of %s is not at %s.', $serial, $sku, $location));
    }

    public static function waveReleased(string $code): self
    {
        return new self(\sprintf('The wave %s is released: its orders are shipping.', $code));
    }

    public static function unknownWave(string $code): self
    {
        return new self(\sprintf('No shipping wave is known as "%s".', $code));
    }

    public static function unknownConvention(string $code): self
    {
        return new self(\sprintf('No convention is known as "%s".', $code));
    }

    public static function noPolicies(): self
    {
        return new self('Backorders are set through a StockPolicyRepository: none is wired.');
    }

    public static function unknownOrder(string $orderNumber): self
    {
        return new self(\sprintf('No order is known as "%s".', $orderNumber));
    }
}
