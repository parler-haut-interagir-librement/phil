<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Stockroom;

use Closure;
use Override;
use Phil\LibraryBundle\Application\Contract\StockLink;
use Phil\Stockroom\Place;
use Phil\Stockroom\Stockroom;
use Phil\StockroomBundle\Infrastructure\Dbal\StockroomTransactions;

/**
 * The stock moves of the loans through `phil/stockroom`: a copy handed over goes out to the loan as its holder, comes
 * back to its item's home, or is recorded lost there, each movement referring to the loan. The member is never named in
 * the stockroom's journal. With the transactions of `phil/stockroom-bundle`, each movement runs in one, the row of its
 * item locked; it joins the loan's transaction when both are on one connection.
 */
final readonly class StockroomLink implements StockLink
{
    public function __construct(
        private Stockroom $stockroom,
        private ?StockroomTransactions $transactions = null,
    ) {
    }

    #[Override]
    public function out(string $item, string $loan): void
    {
        $this->locked($item, fn (): mixed => $this->stockroom->issue($item, Place::holder($loan), reference: $loan));
    }

    #[Override]
    public function back(string $item, string $loan): void
    {
        $this->locked($item, fn (): mixed => $this->stockroom->giveBack($item, Place::holder($loan), reference: $loan));
    }

    #[Override]
    public function lost(string $item, string $loan): void
    {
        $this->locked($item, fn (): mixed => $this->stockroom->lose($item, Place::holder($loan), reference: $loan));
    }

    /**
     * @param Closure(): mixed $move
     */
    private function locked(string $item, Closure $move): void
    {
        if ($this->transactions instanceof StockroomTransactions) {
            $this->transactions->run($move, $item);

            return;
        }

        $move();
    }
}
