<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Tests\Unit;

use Override;
use Phil\UsageMeter\Counter;
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\UsageStore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryUsageStore::class)]
#[CoversClass(Counter::class)]
final class InMemoryUsageStoreTest extends UsageStoreTestCase
{
    #[Override]
    protected function store(): UsageStore
    {
        return new InMemoryUsageStore();
    }
}
