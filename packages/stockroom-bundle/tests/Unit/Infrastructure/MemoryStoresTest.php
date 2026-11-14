<?php

declare(strict_types=1);

namespace Phil\StockroomBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\StockroomBundle\Tests\Unit\Fixture\Stores;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * The contract held by the adapters in memory of `phil/stockroom`, the reference the bundle's adapters are measured by.
 */
#[CoversNothing]
final class MemoryStoresTest extends StoresTestCase
{
    #[Override]
    protected function stores(): Stores
    {
        return Stores::memory();
    }
}
