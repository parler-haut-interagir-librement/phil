<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Override;
use Phil\CommerceConsignmentBundle\Application\Contract\StockMovements;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;

/**
 * Stock movements written down instead of made, with the units on hand a test sets.
 */
final class RecordingStockMovements implements StockMovements
{
    /** @var list<string> */
    public array $calls = [];

    /** @var array<string, int> */
    public array $onHand = [];

    #[Override]
    public function receive(Receipt $receipt, DateTimeImmutable $at): void
    {
        $this->calls[] = \sprintf('receive %s at %s', $receipt->reference, $receipt->location);
    }

    #[Override]
    public function transfer(string $sku, string $from, string $to, int $quantity, DateTimeImmutable $at, ?string $reference): void
    {
        $this->calls[] = \sprintf('transfer %d %s from %s to %s (%s)', $quantity, $sku, $from, $to, $reference ?? '-');
    }

    #[Override]
    public function adjust(string $sku, string $location, int $difference, DateTimeImmutable $at, ?string $reference): void
    {
        $this->calls[] = \sprintf('adjust %s at %s by %d (%s)', $sku, $location, $difference, $reference ?? '-');
    }

    #[Override]
    public function restock(string $sku, string $location, int $quantity, DateTimeImmutable $at, string $reference): void
    {
        $this->calls[] = \sprintf('restock %d %s at %s (%s)', $quantity, $sku, $location, $reference);
    }

    #[Override]
    public function giveBack(string $sku, string $location, int $quantity, DateTimeImmutable $at, string $reference): void
    {
        $this->calls[] = \sprintf('give back %d %s at %s (%s)', $quantity, $sku, $location, $reference);
    }

    #[Override]
    public function onHand(string $sku, string $location): int
    {
        return $this->onHand[$sku.'@'.$location] ?? 0;
    }
}
