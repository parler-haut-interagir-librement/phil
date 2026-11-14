<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Integration;

use DateTimeImmutable;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Exception\RequestInProgress;
use Phil\Idempotency\Exception\ReservationLost;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\Store\CacheIdempotencyStore;
use Phil\Idempotency\Store\DbalIdempotencyStore;
use Phil\Idempotency\StoredResponse;
use Phil\Idempotency\Tests\Unit\Store\DbalIdempotencyStoreTest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * A first run outlives its reservation; a second run with the same key starts meanwhile. Whatever the first run then
 * reports — a failure or a response —, the second run keeps the key, and a third request waits for it instead of
 * running beside it.
 */
#[CoversClass(IdempotencyGuard::class)]
#[CoversClass(DbalIdempotencyStore::class)]
#[CoversClass(CacheIdempotencyStore::class)]
final class OutlivedReservationTest extends TestCase
{
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-11 12:00:00 UTC'));
    }

    #[TestWith(['dbal', true])]
    #[TestWith(['dbal', false])]
    #[TestWith(['cache', true])]
    #[TestWith(['cache', false])]
    public function testTheLateEndOfTheFirstRunLeavesTheSecondOneAlone(string $adapter, bool $firstFails): void
    {
        $guard = new IdempotencyGuard($this->store($adapter), $this->clock, 86_400, 300);
        [$scope, $key, $fingerprint] = [IdempotencyScope::of('client:1'), IdempotencyKey::fromString('k-1'), PayloadFingerprint::ofParts('order', '1')];
        $runs = [];

        try {
            $guard->run($scope, $key, $fingerprint, function () use ($guard, $scope, $key, $fingerprint, $firstFails, &$runs): StoredResponse {
                $runs[] = 'first';
                $this->clock->sleep(301);

                $second = $guard->begin($scope, $key, $fingerprint);
                self::assertNotNull($second->reservation, 'The expired reservation still kept its key.');
                $runs[] = 'second';

                if ($firstFails) {
                    throw new RuntimeException('First run failed.');
                }

                return StoredResponse::of(201, [], 'first');
            });
            self::fail('The end of the first run went unnoticed.');
        } catch (ReservationLost) {
            self::assertFalse($firstFails);
        } catch (RuntimeException $runtimeException) {
            self::assertTrue($firstFails);
            self::assertSame('First run failed.', $runtimeException->getMessage());
        }

        try {
            $guard->run($scope, $key, $fingerprint, static fn (): StoredResponse => throw new RuntimeException('Ran beside the second run.'));
            self::fail('A third request ran while the second run holds the key.');
        } catch (RequestInProgress) {
        }

        self::assertSame(['first', 'second'], $runs);
    }

    private function store(string $adapter): IdempotencyStore
    {
        return 'dbal' === $adapter
            ? new DbalIdempotencyStore(DbalIdempotencyStoreTest::connection(), $this->clock)
            : new CacheIdempotencyStore(new ArrayAdapter(clock: $this->clock), new LockFactory(new InMemoryStore()), $this->clock);
    }
}
