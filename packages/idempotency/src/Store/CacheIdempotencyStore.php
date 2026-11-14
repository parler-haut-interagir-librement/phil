<?php

declare(strict_types=1);

namespace Phil\Idempotency\Store;

use DateTimeImmutable;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\Exception\ReservationLost;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyReservation;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\IdempotencyState;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * The keys in a PSR-6 cache pool, each write under a lock of the Symfony Lock component.
 *
 * A pool has no atomic insert: the lock of the key makes the read and the write of `reserve()` one step, so that two
 * concurrent runs never both reserve. The pool and the lock store must be shared by every server that answers the
 * same keys — Redis, Memcached, a database —, never a local filesystem behind a load balancer.
 *
 * Each entry holds scalars only, never an object, and expires with its record: the pool forgets it on its own.
 *
 * PSR-6 has no compare-and-set either: the completion and the failure read the token of the entry and write the
 * outcome under the same lock, and throw `ReservationLost` when the entry holds another token or none. The check
 * holds as long as every writer of the pool takes these locks, and a write ends within the lifetime of its lock.
 * A reservation past its expiry may already be gone from the pool, unlike a row of `DbalIdempotencyStore`: its
 * completion is then lost too.
 */
final readonly class CacheIdempotencyStore implements IdempotencyStore
{
    /** The longest a write holds the lock of its key, in seconds. */
    private const float LOCK_TTL = 10.0;

    public function __construct(
        private CacheItemPoolInterface $pool,
        private LockFactory $locks,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function find(IdempotencyScope $scope, IdempotencyKey $key): ?IdempotencyRecord
    {
        return $this->read($this->name($scope, $key));
    }

    #[Override]
    public function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): ?IdempotencyReservation
    {
        $name = $this->name($scope, $key);

        return $this->locked($name, function () use ($name, $scope, $key, $fingerprint, $expiresAt): ?IdempotencyReservation {
            $existing = $this->read($name);

            if ($existing instanceof IdempotencyRecord && IdempotencyState::Failed !== $existing->state) {
                return null;
            }

            $reservation = IdempotencyReservation::create($scope, $key);
            $this->write($name, IdempotencyRecord::inProgress($fingerprint, $expiresAt), $reservation->token);

            return $reservation;
        });
    }

    #[Override]
    public function complete(IdempotencyReservation $reservation, StoredResponse $response, DateTimeImmutable $expiresAt): void
    {
        $name = $this->name($reservation->scope, $reservation->key);

        $this->locked($name, function () use ($name, $reservation, $response, $expiresAt): void {
            $this->write($name, IdempotencyRecord::completed($this->held($name, $reservation), $response, $expiresAt), $reservation->token);
        });
    }

    #[Override]
    public function fail(IdempotencyReservation $reservation, DateTimeImmutable $expiresAt): void
    {
        $name = $this->name($reservation->scope, $reservation->key);

        $this->locked($name, function () use ($name, $reservation, $expiresAt): void {
            $this->write($name, IdempotencyRecord::failed($this->held($name, $reservation), $expiresAt), $reservation->token);
        });
    }

    #[Override]
    public function release(IdempotencyScope $scope, IdempotencyKey $key): void
    {
        $this->pool->deleteItem($this->name($scope, $key));
    }

    /**
     * The cache key of a scope and a key: a digest, since a scope or a key may hold characters PSR-6 reserves.
     */
    private function name(IdempotencyScope $scope, IdempotencyKey $key): string
    {
        return 'phil_idempotency.'.hash('sha256', \strlen($scope->value).':'.$scope->value.$key->value);
    }

    /**
     * @template T
     *
     * @param callable(): T $write
     *
     * @return T
     */
    private function locked(string $name, callable $write): mixed
    {
        $lock = $this->locks->createLock($name, self::LOCK_TTL);
        $lock->acquire(true);

        try {
            return $write();
        } finally {
            $lock->release();
        }
    }

    /**
     * The fingerprint of the entry `$reservation` still holds, read under its lock, whatever the application's clock
     * says of its expiry: a reservation no other run replaced still holds its key.
     */
    private function held(string $name, IdempotencyReservation $reservation): PayloadFingerprint
    {
        $item = $this->pool->getItem($name);
        $entry = $item->isHit() ? $item->get() : null;

        if (!\is_array($entry) || ($entry['token'] ?? null) !== $reservation->token || !\is_string($entry['fingerprint'] ?? null)) {
            throw ReservationLost::toAnotherRun();
        }

        return PayloadFingerprint::fromHex($entry['fingerprint']);
    }

    private function read(string $name): ?IdempotencyRecord
    {
        $item = $this->pool->getItem($name);
        $entry = $item->isHit() ? $item->get() : null;

        if (!\is_array($entry)) {
            return null;
        }

        $fingerprint = $entry['fingerprint'] ?? null;
        $state = \is_string($entry['state'] ?? null) ? IdempotencyState::tryFrom($entry['state']) : null;
        $expiresAt = $entry['expires_at'] ?? null;

        // The pool's own expiry follows the system clock; the record's follows the application's.
        if (!\is_string($fingerprint) || !$state instanceof IdempotencyState || !\is_int($expiresAt) || $expiresAt <= $this->clock->now()->getTimestamp()) {
            return null;
        }

        $response = null;

        if (IdempotencyState::Completed === $state) {
            /** @var array{status: int, headers: array<string, list<string>>, body: string} $entry written by write() */
            $response = StoredResponse::of($entry['status'], $entry['headers'], $entry['body']);
        }

        return IdempotencyRecord::of(PayloadFingerprint::fromHex($fingerprint), $state, new DateTimeImmutable('@'.$expiresAt), $response);
    }

    private function write(string $name, IdempotencyRecord $record, string $token): void
    {
        $entry = [
            'fingerprint' => $record->fingerprint->hex,
            'state' => $record->state->value,
            'expires_at' => $record->expiresAt->getTimestamp(),
            'token' => $token,
        ];

        if ($record->response instanceof StoredResponse) {
            $entry += ['status' => $record->response->status, 'headers' => $record->response->headers, 'body' => $record->response->body];
        }

        $item = $this->pool->getItem($name);
        $this->pool->save($item->set($entry)->expiresAt($record->expiresAt));
    }
}
