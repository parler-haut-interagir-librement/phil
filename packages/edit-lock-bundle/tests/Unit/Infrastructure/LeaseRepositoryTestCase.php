<?php

declare(strict_types=1);

namespace Phil\EditLockBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\EditLockBundle\Domain\Model\Lease;
use Phil\EditLockBundle\Domain\Repository\LeaseRepository;
use Phil\EditLockBundle\Domain\ValueObject\Holder;
use Phil\EditLockBundle\Domain\ValueObject\LeaseKey;
use PHPUnit\Framework\TestCase;

/**
 * What every store does: the same tests on each.
 */
abstract class LeaseRepositoryTestCase extends TestCase
{
    abstract protected function store(): LeaseRepository;

    public function testALeaseIsInsertedOnce(): void
    {
        $store = $this->store();
        $now = new DateTimeImmutable('2026-11-06 10:00:00 America/Montreal');
        $lease = Lease::grant(LeaseKey::of('page', 'b1'), Holder::of('bob', 'Bob'), $now, 30);

        self::assertSame([null], [$store->get(LeaseKey::of('page', 'b1'))]);
        self::assertTrue($store->insert($lease));
        self::assertFalse($store->insert(Lease::grant(LeaseKey::of('page', 'b1'), Holder::of('cy', 'Cy'), $now, 30)));
        self::assertTrue($store->insert(Lease::grant(LeaseKey::of('page'), Holder::of('cy', 'Cy'), $now, 30)));

        $kept = $store->get(LeaseKey::of('page', 'b1'));
        self::assertNotNull($kept);
        self::assertSame(['page#b1', 'bob', 'Bob', '2026-11-06T15:00:00+00:00', '2026-11-06T15:00:30+00:00', 1], [
            $kept->key()->toString(), $kept->holder()->id, $kept->holder()->name, $kept->acquiredAt()->format('c'), $kept->expiresAt()->format('c'), $kept->version,
        ]);
        self::assertSame('page', $store->get(LeaseKey::of('page'))?->key()->toString());
        self::assertNull($store->get(LeaseKey::of('page', 'b2')));
        self::assertNull($store->get(LeaseKey::of('other', 'b1')));
    }

    public function testALeaseIsReplacedOnTheVersionRead(): void
    {
        $store = $this->store();
        $now = new DateTimeImmutable('2026-11-06 15:00:00 UTC');
        $lease = Lease::grant(LeaseKey::of('page', 'b1'), Holder::of('bob', 'Bob'), $now, 30);
        $store->insert($lease);
        $renewed = $lease->renewed(Holder::of('bob', 'Bobby'), $now->modify('+10 seconds'), 30);
        $other = Lease::grant(LeaseKey::of('page', 'b2'), Holder::of('bob', 'Bob'), $now, 30);
        $elsewhere = Lease::grant(LeaseKey::of('pages', 'b1'), Holder::of('bob', 'Bob'), $now, 30);
        $store->insert($elsewhere);

        self::assertTrue($store->replace($lease, $renewed));
        self::assertFalse($store->replace($lease, $lease->passedTo(Holder::of('cy', 'Cy'), $now, 30)));
        self::assertFalse($store->replace($other, $other->renewed(Holder::of('bob', 'Bob'), $now, 30)));

        $kept = $store->get(LeaseKey::of('page', 'b1'));
        self::assertSame(['bob', 'Bobby', '15:00:00', '15:00:40', 2], [$kept?->holder()->id, $kept?->holder()->name, $kept?->acquiredAt()->format('H:i:s'), $kept?->expiresAt()->format('H:i:s'), $kept?->version]);
        self::assertTrue($store->replace($renewed, $renewed->passedTo(Holder::of('cy', 'Cy'), $now->modify('+1 minute'), 30)));
        self::assertSame(['cy', '15:01:00', 3], [$store->get(LeaseKey::of('page', 'b1'))?->holder()->id, $store->get(LeaseKey::of('page', 'b1'))?->acquiredAt()->format('H:i:s'), $store->get(LeaseKey::of('page', 'b1'))?->version]);
        self::assertNull($store->get(LeaseKey::of('page', 'b2')));
        self::assertSame(['bob', 'Bob', 1], [$store->get(LeaseKey::of('pages', 'b1'))?->holder()->id, $store->get(LeaseKey::of('pages', 'b1'))?->holder()->name, $store->get(LeaseKey::of('pages', 'b1'))?->version]);
    }

    public function testOnlyItsHolderDeletesALease(): void
    {
        $store = $this->store();
        $now = new DateTimeImmutable('2026-11-06 15:00:00 UTC');
        $store->insert(Lease::grant(LeaseKey::of('page', 'b1'), Holder::of('bob', 'Bob'), $now, 30));
        $store->insert(Lease::grant(LeaseKey::of('page'), Holder::of('bob', 'Bob'), $now, 30));
        $store->insert(Lease::grant(LeaseKey::of('pages', 'b1'), Holder::of('bob', 'Bob'), $now, 30));

        self::assertFalse($store->delete(LeaseKey::of('page', 'b1'), 'cy'));
        self::assertFalse($store->delete(LeaseKey::of('page', 'b2'), 'bob'));
        self::assertTrue($store->delete(LeaseKey::of('page', 'b1'), 'bob'));
        self::assertFalse($store->delete(LeaseKey::of('page', 'b1'), 'bob'));
        self::assertNull($store->get(LeaseKey::of('page', 'b1')));
        self::assertNotNull($store->get(LeaseKey::of('page')));
        self::assertNotNull($store->get(LeaseKey::of('pages', 'b1')));
    }

    public function testTheLeasesOfAResourceAndTheExpiredOnes(): void
    {
        $store = $this->store();
        $now = new DateTimeImmutable('2026-11-06 15:00:00 UTC');
        $store->insert(Lease::grant(LeaseKey::of('page', 'zz'), Holder::of('bob', 'Bob'), $now, 30));
        $store->insert(Lease::grant(LeaseKey::of('page', 'aa'), Holder::of('cy', 'Cy'), $now, 10));
        $store->insert(Lease::grant(LeaseKey::of('page'), Holder::of('bob', 'Bob'), $now, 20));
        $store->insert(Lease::grant(LeaseKey::of('pages', 'aa'), Holder::of('bob', 'Bob'), $now, 5));

        self::assertSame(['page', 'page#aa', 'page#zz'], array_map(static fn (Lease $lease): string => $lease->key()->toString(), $store->ofResource('page')));
        self::assertSame([], $store->ofResource('nothing'));
        self::assertSame(0, $store->deleteExpired($now->modify('+4 seconds')));
        self::assertSame(2, $store->deleteExpired($now->modify('+10 seconds')));
        self::assertSame(['page', 'page#zz'], array_map(static fn (Lease $lease): string => $lease->key()->toString(), $store->ofResource('page')));
        self::assertSame([], $store->ofResource('pages'));
        self::assertSame(2, $store->deleteExpired(new DateTimeImmutable('2026-11-06 10:00:30 America/Montreal')));
        self::assertSame([], $store->ofResource('page'));
    }
}
