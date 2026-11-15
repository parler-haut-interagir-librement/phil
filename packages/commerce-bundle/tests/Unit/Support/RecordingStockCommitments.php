<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceBundle\Application\Contract\StockCommitments;

/**
 * Records what it is asked to hold and to free, as `commit 1` and `release 1`.
 */
final class RecordingStockCommitments implements StockCommitments
{
    /** @var list<string> */
    public array $calls = [];

    #[Override]
    public function commit(string $orderNumber): void
    {
        $this->calls[] = 'commit '.$orderNumber;
    }

    #[Override]
    public function release(string $orderNumber): void
    {
        $this->calls[] = 'release '.$orderNumber;
    }
}
