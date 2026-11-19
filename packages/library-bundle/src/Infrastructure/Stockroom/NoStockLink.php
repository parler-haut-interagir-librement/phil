<?php

declare(strict_types=1);

namespace Phil\LibraryBundle\Infrastructure\Stockroom;

use Override;
use Phil\LibraryBundle\Application\Contract\StockLink;

/**
 * No stockroom: the loans move nothing, even for a copy linked to an item.
 */
final readonly class NoStockLink implements StockLink
{
    #[Override]
    public function out(string $item, string $loan): void
    {
    }

    #[Override]
    public function back(string $item, string $loan): void
    {
    }

    #[Override]
    public function lost(string $item, string $loan): void
    {
    }
}
