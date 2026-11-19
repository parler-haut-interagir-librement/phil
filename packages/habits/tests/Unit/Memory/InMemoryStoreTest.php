<?php

declare(strict_types=1);

namespace Phil\Habits\Tests\Unit\Memory;

use Override;
use Phil\Habits\Contract\CheckInStore;
use Phil\Habits\Contract\HabitStore;
use Phil\Habits\Memory\InMemoryCheckInStore;
use Phil\Habits\Memory\InMemoryHabitStore;
use Phil\Habits\Tests\Unit\StoreTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryHabitStore::class)]
#[CoversClass(InMemoryCheckInStore::class)]
final class InMemoryStoreTest extends StoreTestCase
{
    #[Override]
    protected function habitStore(): HabitStore
    {
        return new InMemoryHabitStore();
    }

    #[Override]
    protected function checkInStore(): CheckInStore
    {
        return new InMemoryCheckInStore();
    }
}
