<?php

declare(strict_types=1);

namespace Phil\Idempotency\Store;

use DateTimeImmutable;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
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
    public function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): bool
    {
        $name = $this->name($scope, $key);

        return $this->locked($name, function () use ($name, $fingerprint, $expiresAt): bool {
            $existing = $this->read($name);

            if ($existing instanceof IdempotencyRecord && IdempotencyState::Failed !== $existing->state) {
                return false;
            }

            $this->write($name, IdempotencyRecord::inProgress($fingerprint, $expiresAt));

            return true;
        });
    }

    #[Override]
    public function complete(IdempotencyScope $scope, IdempotencyKey $key, StoredResponse $response, DateTimeImmutable $expiresAt): void
    {
        $name = $this->name($scope, $key);

        $this->locked($name, function () use ($name, $response, $expiresAt): void {
            $existing = $this->read($name);

            if ($existing instanceof IdempotencyRecord) {
                $this->write($name, IdempotencyRecord::completed($existing->fingerprint, $response, $expiresAt));
            }
        });
    }

    #[Override]
    public function fail(IdempotencyScope $scope, IdempotencyKey $key, DateTimeImmutable $expiresAt): void
    {
        $name = $this->name($scope, $key);

        $this->locked($name, function () use ($name, $expiresAt): void {
            $existing = $this->read($name);

            if ($existing instanceof IdempotencyRecord) {
                $this->write($name, IdempotencyRecord::failed($existing->fingerprint, $expiresAt));
            }
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

    private function write(string $name, IdempotencyRecord $record): void
    {
        $entry = [
            'fingerprint' => $record->fingerprint->hex,
            'state' => $record->state->value,
            'expires_at' => $record->expiresAt->getTimestamp(),
        ];

        if ($record->response instanceof StoredResponse) {
            $entry += ['status' => $record->response->status, 'headers' => $record->response->headers, 'body' => $record->response->body];
        }

        $item = $this->pool->getItem($name);
        $this->pool->save($item->set($entry)->expiresAt($record->expiresAt));
    }
}
