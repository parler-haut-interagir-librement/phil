<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Store;

use ArrayObject;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Exception\ReservationLost;
use Phil\Idempotency\IdempotencyReservation;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\Idempotency\StoredResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(CacheIdempotencyStore::class)]
final class CacheIdempotencyStoreTest extends IdempotencyStoreTestCase
{
    private ArrayAdapter $pool;

    private InMemoryStore $locks;

    protected function setUp(): void
    {
        $this->locks = new InMemoryStore();
        $this->pool = new ArrayAdapter();
        parent::setUp();
    }

    #[Override]
    protected function createStore(MockClock $clock): IdempotencyStore
    {
        // The pool reads the store's clock, set by parent::setUp(): its entries expire at the store's time, not the
        // machine's.
        $this->pool = new ArrayAdapter(clock: $clock);

        return new CacheIdempotencyStore($this->pool, new LockFactory($this->locks), $clock);
    }

    public function testAnEntryHoldsScalarsUnderADigestOfItsScopeAndKey(): void
    {
        [$scope, $key, $fingerprint] = $this->key();

        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        $name = 'phil_idempotency.'.hash('sha256', '8:user:adak1');
        self::assertSame([$name], array_keys($this->pool->getValues()));
        self::assertSame(['fingerprint' => $fingerprint->hex, 'state' => 'in_progress', 'expires_at' => $this->at('2026-11-11 12:05')->getTimestamp(), 'token' => $reservation->token], $this->pool->getItem($name)->get());
    }

    public function testTheLockOfAKeyIsFreedAfterEachWrite(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        $lock = new LockFactory($this->locks)->createLock('phil_idempotency.'.hash('sha256', '8:user:adak1'));

        self::assertTrue($lock->acquire(false), 'The reservation left its lock behind.');
        $lock->release();
    }

    public function testEachWriteWaitsForTheLockOfItsKeyThenFreesIt(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects(self::exactly(3))->method('acquire')->with(true)->willReturn(true);
        $lock->expects(self::exactly(3))->method('release');
        $locks = $this->createMock(LockFactory::class);
        $locks->expects(self::exactly(3))->method('createLock')->with('phil_idempotency.'.hash('sha256', '8:user:adak1'), 10.0)->willReturn($lock);
        $store = new CacheIdempotencyStore($this->pool, $locks, $this->clock);

        $reservation = $store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        self::assertInstanceOf(IdempotencyReservation::class, $reservation);
        $store->complete($reservation, StoredResponse::nothing(), $this->at('2026-11-12 12:00'));
        $store->fail($reservation, $this->at('2026-11-12 12:00'));
    }

    public function testAnEntryTheStoreDidNotWriteIsAbsent(): void
    {
        [$scope, $key] = $this->key();
        $name = 'phil_idempotency.'.hash('sha256', '8:user:adak1');

        // An object that reads like the array the store writes is not one either.
        $lookalike = new ArrayObject(['fingerprint' => str_repeat('a', 64), 'state' => 'failed', 'expires_at' => \PHP_INT_MAX]);

        foreach (['text', ['fingerprint' => 'f', 'state' => 'unknown', 'expires_at' => \PHP_INT_MAX], ['state' => 'failed'], $lookalike] as $entry) {
            $this->pool->save($this->pool->getItem($name)->set($entry));

            self::assertNull($this->store->find($scope, $key));
        }
    }

    /**
     * The record expires at the application's time even when the pool, on another clock, still holds it: at its
     * expiry and after, it is absent.
     */
    public function testARecordTheApplicationClockExpiredIsAbsentFromAPoolThatKeepsIt(): void
    {
        [$scope, $key] = $this->key();
        $name = 'phil_idempotency.'.hash('sha256', '8:user:adak1');
        $this->pool = new ArrayAdapter();
        $store = new CacheIdempotencyStore($this->pool, new LockFactory($this->locks), $this->clock);
        $now = $this->clock->now()->getTimestamp();

        foreach ([$now, $now - 60] as $expiresAt) {
            $this->pool->save($this->pool->getItem($name)->set(['fingerprint' => str_repeat('a', 64), 'state' => 'failed', 'expires_at' => $expiresAt]));

            self::assertNull($store->find($scope, $key));
        }

        $this->pool->save($this->pool->getItem($name)->set(['fingerprint' => str_repeat('a', 64), 'state' => 'failed', 'expires_at' => $now + 1]));
        self::assertNotNull($store->find($scope, $key));
    }

    /**
     * The pool forgets an entry at its expiry: a reservation past it, no longer in the pool, has lost its key. A pool
     * that still holds it, on another clock, still lets it complete.
     */
    #[Override]
    public function testAReservationNoOtherRunReplacedCompletesPastItsExpiry(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $this->clock->modify('2026-11-11 12:05:00 UTC');

        try {
            $this->store->complete($reservation, StoredResponse::nothing(), $this->at('2026-11-12 12:00'));
            self::fail('An entry the pool forgot was written.');
        } catch (ReservationLost) {
        }

        // The pool's own clock stays before the expiry.
        $this->pool = new ArrayAdapter(clock: new MockClock('2026-11-11 12:00:00 UTC'));
        $this->store = new CacheIdempotencyStore($this->pool, new LockFactory($this->locks), $this->clock);
        $this->clock->modify('2026-11-11 12:00:00 UTC');
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $this->clock->modify('2026-11-11 12:05:00 UTC');

        $this->store->complete($reservation, StoredResponse::of(200, [], 'late but alone'), $this->at('2026-11-12 12:00'));

        self::assertSame('late but alone', $this->store->find($scope, $key)?->response?->body);
    }

    public function testAnEntryWithoutAFingerprintHoldsNoReservation(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $name = 'phil_idempotency.'.hash('sha256', '8:user:adak1');

        foreach (['text', ['token' => $reservation->token], ['token' => $reservation->token, 'fingerprint' => 7]] as $entry) {
            $this->pool->save($this->pool->getItem($name)->set($entry));

            try {
                $this->store->fail($reservation, $this->at('2026-11-12 12:00'));
                self::fail('An entry without a fingerprint was written.');
            } catch (ReservationLost) {
            }
        }
    }
}
