<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Application\Contract;

/**
 * Moves the stockroom item a copy is, as its loans go: out to the loan when it is handed over, back to its home when it
 * returns, lost with the copy. The holder in the stockroom is the loan, never the member.
 */
interface StockLink
{
    public function out(string $item, string $loan): void;

    public function back(string $item, string $loan): void;

    public function lost(string $item, string $loan): void;
}
