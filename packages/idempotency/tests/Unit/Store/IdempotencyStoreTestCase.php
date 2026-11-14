<?php

declare(strict_types=1);

namespace Phil\Idempotency\Tests\Unit\Store;

use DateTimeImmutable;
use DateTimeZone;
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
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 07:05', 'America/Toronto'));
        self::assertSame([$scope, $key, IdempotencyReservation::TOKEN_LENGTH], [$reservation->scope, $reservation->key, \strlen($reservation->token)]);
        self::assertNull($this->store->reserve($scope, $key, PayloadFingerprint::ofParts('other'), $this->at('2026-11-12 12:00')), 'A live reservation keeps its key.');

        $record = $this->store->find($scope, $key);
        self::assertInstanceOf(IdempotencyRecord::class, $record);
        self::assertSame([$fingerprint->hex, IdempotencyState::InProgress, null], [$record->fingerprint->hex, $record->state, $record->response]);
        self::assertSame($this->at('2026-11-11 12:05')->getTimestamp(), $record->expiresAt->getTimestamp());
        self::assertNull($this->store->find(IdempotencyScope::of('other'), $key), 'Another scope never shares the key.');
        self::assertNull($this->store->find($scope, IdempotencyKey::fromString('k2')));

        $this->store->complete($reservation, StoredResponse::of(201, ['Location' => ['/orders/1']], "\x00{\"id\":1}"), $this->at('2026-11-12 07:00', 'America/Toronto'));
        $completed = $this->store->find($scope, $key);
        self::assertSame(IdempotencyState::Completed, $completed?->state);
        self::assertSame([$fingerprint->hex, 201, ['Location' => ['/orders/1']], "\x00{\"id\":1}"], [$completed->fingerprint->hex, $completed->response?->status, $completed->response?->headers, $completed->response?->body]);
        self::assertSame($this->at('2026-11-12 12:00')->getTimestamp(), $completed->expiresAt->getTimestamp(), 'The answered key is kept for its retention, past the reservation.');
        self::assertNull($this->store->reserve($scope, $key, $fingerprint, $this->at('2026-11-13 12:00')), 'A completed key keeps its answer.');

        $this->clock->modify('2026-11-12 11:59:59 UTC');
        self::assertNotNull($this->store->find($scope, $key)?->response);

        $this->store->release($scope, $key);
        self::assertNull($this->store->find($scope, $key));
    }

    public function testEachReservationHasItsOwnToken(): void
    {
        [$scope, $key, $fingerprint] = $this->key();

        $first = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $other = $this->reserve($scope, IdempotencyKey::fromString('k2'), $fingerprint, $this->at('2026-11-11 12:05'));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $first->token);
        self::assertNotSame($first->token, $other->token);
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
        self::assertNotNull($this->store->reserve($scope, $key, $other, $this->at('2026-11-11 12:10')));
        self::assertSame($other->hex, $this->store->find($scope, $key)?->fingerprint->hex);
    }

    public function testAFailedKeyKeepsItsFingerprintAndCanBeReservedAgain(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        $this->store->fail($reservation, $this->at('2026-11-12 12:00'));

        $failed = $this->store->find($scope, $key);
        self::assertSame([IdempotencyState::Failed, $fingerprint->hex, null], [$failed?->state, $failed?->fingerprint->hex, $failed?->response]);
        self::assertSame($this->at('2026-11-12 12:00')->getTimestamp(), $failed?->expiresAt->getTimestamp());

        $again = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        self::assertNotSame($reservation->token, $again->token);
        self::assertSame(IdempotencyState::InProgress, $this->store->find($scope, $key)?->state);

        $this->expectException(ReservationLost::class);
        $this->store->complete($reservation, StoredResponse::nothing(), $this->at('2026-11-12 12:00'));
    }

    public function testAFailureAfterAnAnswerForgetsTheAnswer(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $this->store->complete($reservation, StoredResponse::of(200, [], 'ok'), $this->at('2026-11-12 12:00'));

        $this->store->fail($reservation, $this->at('2026-11-12 12:00'));

        self::assertNull($this->store->find($scope, $key)?->response);
    }

    public function testTheSameOutcomeCanBeWrittenTwice(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        $this->store->fail($reservation, $this->at('2026-11-12 12:00'));
        $this->store->fail($reservation, $this->at('2026-11-12 12:00'));
        $this->store->complete($reservation, StoredResponse::of(200, [], 'ok'), $this->at('2026-11-12 12:00'));
        $this->store->complete($reservation, StoredResponse::of(200, [], 'ok'), $this->at('2026-11-12 12:00'));

        self::assertSame('ok', $this->store->find($scope, $key)?->response?->body);
    }

    /**
     * The defect this guards against: a run outlives its reservation, a second run reserves the key, and the first
     * one then ends. Its outcome must not land on the second run's reservation.
     */
    public function testARunThatOutlivedItsReservationCannotOverwriteTheNextOne(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $first = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));

        $this->clock->modify('2026-11-11 12:06 UTC');
        $second = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:11'));

        foreach ([
            fn () => $this->store->fail($first, $this->at('2026-11-12 12:00')),
            fn () => $this->store->complete($first, StoredResponse::of(200, [], 'late'), $this->at('2026-11-12 12:00')),
        ] as $late) {
            try {
                $late();
                self::fail('The outcome of a lost reservation was written.');
            } catch (ReservationLost $reservationLost) {
                self::assertSame('The reservation of this idempotency key expired and the key is no longer held by this run: its outcome was not kept.', $reservationLost->getMessage());
            }

            $record = $this->store->find($scope, $key);
            self::assertSame([IdempotencyState::InProgress, $this->at('2026-11-11 12:11')->getTimestamp()], [$record?->state, $record?->expiresAt->getTimestamp()], 'The second reservation was overwritten.');
        }

        $this->store->complete($second, StoredResponse::of(201, [], 'second'), $this->at('2026-11-12 12:00'));
        self::assertSame('second', $this->store->find($scope, $key)?->response?->body);
    }

    public function testAReleasedKeyIsLostToItsReservation(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $this->store->release($scope, $key);

        foreach ([
            fn () => $this->store->complete($reservation, StoredResponse::nothing(), $this->at('2026-11-12 12:00')),
            fn () => $this->store->fail($reservation, $this->at('2026-11-12 12:00')),
        ] as $write) {
            try {
                $write();
                self::fail('A released key was written.');
            } catch (ReservationLost) {
            }
        }

        self::assertNull($this->store->find($scope, $key));
    }

    public function testAReservationNoOtherRunReplacedCompletesPastItsExpiry(): void
    {
        [$scope, $key, $fingerprint] = $this->key();
        $reservation = $this->reserve($scope, $key, $fingerprint, $this->at('2026-11-11 12:05'));
        $this->clock->modify('2026-11-11 12:05:00 UTC');

        $this->store->complete($reservation, StoredResponse::of(200, [], 'late but alone'), $this->at('2026-11-12 12:00'));

        self::assertSame('late but alone', $this->store->find($scope, $key)?->response?->body);
    }

    /**
     * @return array{IdempotencyScope, IdempotencyKey, PayloadFingerprint}
     */
    protected function key(): array
    {
        return [IdempotencyScope::of('user:ada'), IdempotencyKey::fromString('k1'), PayloadFingerprint::ofRequest('POST', '/a', '{}')];
    }

    /**
     * A reservation that must be taken.
     */
    protected function reserve(IdempotencyScope $scope, IdempotencyKey $key, PayloadFingerprint $fingerprint, DateTimeImmutable $expiresAt): IdempotencyReservation
    {
        $reservation = $this->store->reserve($scope, $key, $fingerprint, $expiresAt);
        self::assertInstanceOf(IdempotencyReservation::class, $reservation);

        return $reservation;
    }

    protected function at(string $moment, string $zone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone($zone));
    }
}
