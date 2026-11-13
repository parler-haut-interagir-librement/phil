<?php

declare(strict_types=1);

namespace Phil\AccessCodes\Tests\Unit;

use DateTimeImmutable;
use Phil\AccessCodes\Batch;
use Phil\AccessCodes\Contract\CodeStore;
use Phil\AccessCodes\Exception\InvalidConfiguration;
use PHPUnit\Framework\TestCase;

/**
 * Every promise of a store, run against each adapter.
 */
abstract class CodeStoreTestCase extends TestCase
{
    protected const string NOW = '2026-11-13 10:00:00';

    abstract protected function store(): CodeStore;

    public function testABatchIsKeptWithItsCodes(): void
    {
        $store = $this->store();
        $batch = $this->batch('b1', 3, '+1 day');
        $store->add($batch, [self::hash(1), self::hash(2), self::hash(3)]);

        $kept = $store->batch('b1');
        self::assertNotNull($kept);
        self::assertSame('b1', $kept->id);
        self::assertSame('Cards b1', $kept->name);
        self::assertSame(3, $kept->size);
        self::assertSame(self::at(0)->getTimestamp(), $kept->createdAt->getTimestamp());
        self::assertSame(self::at(86_400)->getTimestamp(), $kept->expiresAt?->getTimestamp());
        self::assertNull($kept->revokedAt);
        self::assertNull($store->batch('b2'));

        $state = $store->find([self::hash(9), self::hash(2)]);
        self::assertNotNull($state);
        self::assertSame('b1', $state->batchId);
        self::assertSame(self::at(86_400)->getTimestamp(), $state->expiresAt?->getTimestamp());
        self::assertNull($state->redeemedAt);
        self::assertNull($state->redeemedBy);
        self::assertNull($state->revokedAt);
        self::assertNull($store->find([self::hash(9)]));

        $counts = $store->counts('b1');
        self::assertSame([3, 0, 0], [$counts->total, $counts->redeemed, $counts->revoked]);
    }

    public function testTheCountsOfABatchIgnoreTheCodesOfTheOthers(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 2), [self::hash(1), self::hash(2)]);
        $store->add($this->batch('b2', 1), [self::hash(3)]);
        $store->add($this->batch('b3', 1), [self::hash(4)]);
        $store->redeem([self::hash(3)], 'member-7', self::at(10));
        $store->revoke('b1', self::at(20));

        $first = $store->counts('b1');
        $second = $store->counts('b2');
        $third = $store->counts('b3');

        self::assertSame([2, 0, 2], [$first->total, $first->redeemed, $first->revoked]);
        self::assertSame([1, 1, 0], [$second->total, $second->redeemed, $second->revoked]);
        self::assertSame([1, 0, 0], [$third->total, $third->redeemed, $third->revoked]);
    }

    public function testACodeIsRedeemedOnce(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 2), [self::hash(1), self::hash(2)]);

        self::assertSame('b1', $store->redeem([self::hash(9), self::hash(1)], 'member-7', self::at(10)));
        self::assertNull($store->redeem([self::hash(1)], 'member-8', self::at(20)));
        self::assertNull($store->redeem([self::hash(9)], 'member-8', self::at(20)));

        $state = $store->find([self::hash(1)]);
        self::assertNotNull($state);
        self::assertSame(self::at(10)->getTimestamp(), $state->redeemedAt?->getTimestamp());
        self::assertSame('member-7', $state->redeemedBy);
        self::assertNull($store->find([self::hash(2)])?->redeemedAt);

        $counts = $store->counts('b1');
        self::assertSame([2, 1, 0], [$counts->total, $counts->redeemed, $counts->revoked]);
    }

    public function testAnExpiredCodeIsNotRedeemed(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 2, '+1 hour'), [self::hash(1), self::hash(2)]);

        self::assertSame('b1', $store->redeem([self::hash(1)], 'member-7', self::at(3_599)));
        self::assertNull($store->redeem([self::hash(2)], 'member-7', self::at(3_600)));
        self::assertNull($store->find([self::hash(2)])?->redeemedAt);
    }

    public function testARevokedBatchRedeemsNothingMore(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 3), [self::hash(1), self::hash(2), self::hash(3)]);
        $store->add($this->batch('b2', 1), [self::hash(4)]);
        $store->redeem([self::hash(1)], 'member-7', self::at(10));

        self::assertSame(2, $store->revoke('b1', self::at(20)));
        self::assertSame(0, $store->revoke('b1', self::at(30)));
        self::assertSame(0, $store->revoke('unknown', self::at(30)));

        self::assertNull($store->redeem([self::hash(2)], 'member-7', self::at(40)));
        self::assertSame(self::at(20)->getTimestamp(), $store->batch('b1')?->revokedAt?->getTimestamp());
        self::assertSame(self::at(20)->getTimestamp(), $store->find([self::hash(3)])?->revokedAt?->getTimestamp());
        self::assertNull($store->find([self::hash(1)])?->revokedAt);
        self::assertSame(self::at(10)->getTimestamp(), $store->find([self::hash(1)])?->redeemedAt?->getTimestamp());
        self::assertSame('b2', $store->redeem([self::hash(4)], 'member-7', self::at(40)));

        $counts = $store->counts('b1');
        self::assertSame([3, 1, 2], [$counts->total, $counts->redeemed, $counts->revoked]);
        self::assertSame(0, $store->counts('unknown')->total);
    }

    public function testABatchIsStoredWithOneHashPerCode(): void
    {
        $this->expectException(InvalidConfiguration::class);

        $this->store()->add($this->batch('b1', 2), [self::hash(1)]);
    }

    public function testAStoredCodeIsNeverStoredAgain(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 1), [self::hash(1)]);

        try {
            $store->add($this->batch('b2', 2), [self::hash(2), self::hash(1)]);
            self::fail('A code was stored twice.');
        } catch (InvalidConfiguration) {
        }

        self::assertNull($store->batch('b2'));
        self::assertNull($store->find([self::hash(2)]));
    }

    public function testABatchIdentifierIsNeverStoredAgain(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 1), [self::hash(1)]);

        $this->expectException(InvalidConfiguration::class);

        $store->add($this->batch('b1', 1), [self::hash(2)]);
    }

    public function testALargeBatchIsStoredInChunks(): void
    {
        $store = $this->store();
        $store->add($this->batch('b1', 1_201), array_map(self::hash(...), range(1, 1_201)));

        self::assertSame(1_201, $store->counts('b1')->total);
        self::assertSame('b1', $store->redeem([self::hash(1_201)], 'member-7', self::at(1)));
    }

    protected static function at(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW)->modify(\sprintf('+%d seconds', $seconds));
    }

    /**
     * @return non-empty-string
     */
    protected static function hash(int $n): string
    {
        return '$hmac-sha256$v=1$'.hash('sha256', (string) $n);
    }

    protected function batch(string $id, int $size, ?string $expiry = null): Batch
    {
        return new Batch($id, 'Cards '.$id, $size, self::at(0), null === $expiry ? null : self::at(0)->modify($expiry));
    }
}
