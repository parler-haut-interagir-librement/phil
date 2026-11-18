<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Domain;

use Phil\EditLockBundle\Domain\Exception\InvalidLease;
use Phil\EditLockBundle\Domain\ValueObject\Fence;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fence::class)]
final class FenceTest extends TestCase
{
    public function testAFenceNamesTheKeyTheHolderAndTheNumberOfItsClaim(): void
    {
        $fence = Fence::of(LeaseKey::of('page', 'b1'), Holder::of('bob', 'Bob'), 1);

        self::assertSame(['page#b1', 'bob', 1], [$fence->key->toString(), $fence->holder->id, $fence->number]);
    }

    public function testTheGreaterNumberIsTheNewerWrite(): void
    {
        $older = Fence::of(LeaseKey::of('page', 'b1'), Holder::of('bob', 'Bob'), 7);
        $newer = Fence::of(LeaseKey::of('page', 'b1'), Holder::of('ada', 'Ada'), 8);

        self::assertTrue($newer->isNewerThan($older));
        self::assertFalse($older->isNewerThan($newer));
        self::assertFalse($older->isNewerThan($older));
    }

    public function testTheNumberOfAFenceIsPositive(): void
    {
        $this->expectExceptionObject(InvalidLease::because('The number of a fence is a positive number.'));

        Fence::of(LeaseKey::of('page'), Holder::of('bob', 'Bob'), 0);
    }
}
