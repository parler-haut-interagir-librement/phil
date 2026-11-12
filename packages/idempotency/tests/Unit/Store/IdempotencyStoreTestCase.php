<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Store;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\Idempotency\Contract\IdempotencyStore;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyRecord;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\IdempotencyState;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * The contract of `IdempotencyStore`, run against each adapter: a store of the application can extend it too.
 */
abstract class IdempotencyStoreTestCase extends TestCase
{
    protected MockClock $clock;

    protected IdempotencyStore $store;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-11 12:00:00 UTC'));
        $this->store = $this->createStore($this->clock);
    }

    abstract protected function createStore(MockClock $clock): IdempotencyStore;

    public function testAKeyIsReservedCompletedAndFound(): void
    {
        [$scope, $key, $fingerprint] = $this->key();

        self::assertNull($this->store->find($scope, $key));
        self::assertTrue($this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 07:05', 'America/Toronto')));
        self::assertFalse($this->store->reserve($scope, $key, PayloadFingerprint::ofParts('other'), $this->at('2026-11-12 12:00')), 'A live reservation keeps its key.');

        $record = $this->store->find($scope, $key);
        self::assertInstanceOf(IdempotencyRecord::class, $record);
        self::assertSame([$fingerprint->hex, IdempotencyState::InProgress, null], [$record->fingerprint->hex, $record->state, $record->response]);
        self::assertSame($this->at('2026-11-11 12:05')->getTimestamp(), $record->expiresAt->getTimestamp());
        self::assertNull($this->store->find(IdempotencyScope::of('other'), $key), 'Another scope never shares the key.');
        self::assertNull($this->store->find($scope, IdempotencyKey::fromString('k2')));

        $this->store->complete($scope, $key, StoredResponse::of(201, ['Location' => ['/orders/1']], "\x00{\"id\":1}"), $this->at('2026-11-12 07:00', 'America/Toronto'));
        $completed = $this->store->find($scope, $key);
        self::assertSame(IdempotencyState::Completed, $completed?->state);
        self::assertSame([201, ['Location' => ['/orders/1']], "\x00{\"id\":1}"], [$completed->response?->status, $completed->response?->headers, $completed->response?->body]);
        self::assertSame($this->at('2026-11-12 12:00')->getTimestamp(), $completed->expiresAt->getTimestamp(), 'The answered key is kept for its retention, past the reservation.');
        self::assertFalse($this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-13 12:00')), 'A completed key keeps its answer.');

        $this->clock->modify('2026-11-12 11:59:59 UTC');
        self::assertNotNull($this->store->find($scope, $key)?->response);

        $this->store->release($scope, $key);
        self::assertNull($this->store->find($scope, $key));
    }

    public function testAnExpiredKeyIsNewAgain(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        $this->clock->modify('2026-11-11 12:04:59 UTC');
        self::assertNotNull($this->store->find($scope, $key));

        $this->clock->modify('2026-11-11 12:05:00 UTC');
        self::assertNull($this->store->find($scope, $key), 'A record is expired at its expiry.');

        $other = PayloadFingerprint::ofParts('other');
        self::assertTrue($this->store->reserve($scope, $key, $other, $this->at('2026-11-11 12:10')));
        self::assertSame($other->hex, $this->store->find($scope, $key)?->fingerprint->hex);
    }

    public function testAFailedKeyKeepsItsFingerprintAndCanBeReservedAgain(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        $this->store->fail($scope, $key, $this->at('2026-11-12 12:00'));

        $failed = $this->store->find($scope, $key);
        self::assertSame([IdempotencyState::Failed, $fingerprint->hex, null], [$failed?->state, $failed?->fingerprint->hex, $failed?->response]);
        self::assertSame($this->at('2026-11-12 12:00')->getTimestamp(), $failed?->expiresAt->getTimestamp());

        self::assertTrue($this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05')));
        self::assertSame(IdempotencyState::InProgress, $this->store->find($scope, $key)?->state);
    }

    public function testAFailureAfterAnAnswerForgetsTheAnswer(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $this->store->complete($scope, $key, StoredResponse::of(200, [], 'ok'), $this->at('2026-11-12 12:00'));

        $this->store->fail($scope, $key, $this->at('2026-11-12 12:00'));

        self::assertNull($this->store->find($scope, $key)?->response);
    }

    public function testCompletingOrFailingAnUnknownKeyKeepsNothing(): void
    {
        [$scope, $key] = $this->key();

        $this->store->complete($scope, $key, StoredResponse::nothing(), $this->at('2026-11-12 12:00'));
        $this->store->fail($scope, $key, $this->at('2026-11-12 12:00'));
        $this->store->release($scope, $key);

        self::assertNull($this->store->find($scope, $key));
    }

    /**
     * @return array{IdempotencyScope, IdempotencyKey, PayloadFingerprint}
     */
    protected function key(): array
    {
        return [IdempotencyScope::of('user:ada'), IdempotencyKey::fromString('k1'), PayloadFingerprint::ofRequest('POST', '/a', '{}')];
    }

    protected function at(string $moment, string $zone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone($zone));
    }
}
