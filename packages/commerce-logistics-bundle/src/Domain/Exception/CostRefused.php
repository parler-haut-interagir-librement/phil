<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;
use Phil\Money\Money;

/**
 * A cost or a value of stock that cannot be recorded as asked.
 */
final class CostRefused extends DomainException implements DomainError
{
    public static function layer(string $sku, string $location, int $units, Money $value): self
    {
        return new self(\sprintf('A cost layer of %s at %s holds one unit or more, worth zero or more; %d units worth %s %s are not.', $sku, $location, $units, $value->toDecimal(), $value->currency->code));
    }

    public static function overTaken(string $sku, string $location, int $asked, int $held): self
    {
        return new self(\sprintf('%d units of %s cannot leave a cost layer at %s that holds %d.', $asked, $sku, $location, $held));
    }

    public static function negative(string $sku, Money $value): self
    {
        return new self(\sprintf('A cost of %s is zero or more; %s %s is not.', $sku, $value->toDecimal(), $value->currency->code));
    }

    public static function currency(string $sku, string $expected, string $given): self
    {
        return new self(\sprintf('The stock of %s is valued in %s; a cost in %s cannot join it.', $sku, $expected, $given));
    }

    public static function ownedValue(string $sku, string $location): self
    {
        return new self(\sprintf("The units of %s at %s belong to someone else: they carry no value of the shop's.", $sku, $location));
    }

    public static function share(string $sku, string $location, int $units): self
    {
        return new self(\sprintf('The owners given for %d units of %s leaving %s do not add up: each owner gives no more than they hold, and the shop no more than it holds.', $units, $sku, $location));
    }

    public static function notCosted(): self
    {
        return new self('Costing is not enabled: set phil_commerce_logistics.costing to record a landed cost.');
    }

    public static function noLineBears(string $number): self
    {
        return new self(\sprintf("No line of the receipt against %s bears a landed cost: it needs units of the shop's with a unit cost.", $number));
    }

    public static function noLayer(string $sku, string $location, string $reference): self
    {
        return new self(\sprintf('No cost layer of %s at %s holds the units received under %s.', $sku, $location, $reference));
    }
}
