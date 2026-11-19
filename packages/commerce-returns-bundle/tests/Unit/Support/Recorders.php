<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceReturnsBundle\Application\Port\Restocking;
use Phil\CommerceReturnsBundle\Application\Port\StoreCreditGranter;
use Phil\Money\Money;

/**
 * Keeps what it is asked to restock or to credit.
 */
final class Recorders implements Restocking, StoreCreditGranter
{
    /** @var list<array{string, int, string}> */
    public array $restocked = [];

    /** @var list<array{string, int, string, string}> */
    public array $credited = [];

    #[Override]
    public function restock(SellableReference $line, int $quantity, string $return, DateTimeImmutable $at): void
    {
        $this->restocked[] = [$line->value(), $quantity, $return];
    }

    #[Override]
    public function grant(string $customer, Money $amount, DateTimeImmutable $at, string $note, string $orderNumber): void
    {
        $this->credited[] = [$customer, $amount->minorUnits, $note, $orderNumber];
    }
}
