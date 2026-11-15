<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional\App;

use Override;
use Phil\CommerceBundle\Application\Contract\StockCommitments;

/**
 * The orders whose stock is held, as a logistics module would keep them; autoconfiguration plugs it into the
 * deferred sales.
 */
final class HeldStock implements StockCommitments
{
    /** @var array<string, true> */
    public array $held = [];

    #[Override]
    public function commit(string $orderNumber): void
    {
        $this->held[$orderNumber] = true;
    }

    #[Override]
    public function release(string $orderNumber): void
    {
        unset($this->held[$orderNumber]);
    }
}
