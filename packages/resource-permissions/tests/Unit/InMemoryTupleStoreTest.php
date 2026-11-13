<?php

declare(strict_types=1);

namespace Phil\ResourcePermissions\Tests\Unit;

use Override;
use Phil\ResourcePermissions\Contract\TupleStore;
use Phil\ResourcePermissions\InMemoryTupleStore;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryTupleStore::class)]
final class InMemoryTupleStoreTest extends TupleStoreTestCase
{
    #[Override]
    protected function store(): TupleStore
    {
        return new InMemoryTupleStore($this->clock);
    }
}
