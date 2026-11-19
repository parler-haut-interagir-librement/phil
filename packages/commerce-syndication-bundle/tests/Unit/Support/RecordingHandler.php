<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceSyndicationBundle\Application\ForwardedOrder;
use Phil\CommerceSyndicationBundle\Application\Port\ForwardedOrderHandler;

/**
 * Keeps the orders it is handed.
 */
final class RecordingHandler implements ForwardedOrderHandler
{
    /** @var list<ForwardedOrder> */
    public array $handled = [];

    #[Override]
    public function handle(ForwardedOrder $order): void
    {
        $this->handled[] = $order;
    }
}
