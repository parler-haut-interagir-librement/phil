<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Tests\Unit\Support;

use Override;
use Phil\CommerceLogisticsBundle\Application\Port\PrintOnDemand;

final class RecordingPrinter implements PrintOnDemand
{
    /** @var list<array{string, string, int}> */
    public array $printed = [];

    #[Override]
    public function print(string $orderNumber, string $sku, int $quantity): void
    {
        $this->printed[] = [$orderNumber, $sku, $quantity];
    }
}
