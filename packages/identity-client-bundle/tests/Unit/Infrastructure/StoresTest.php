<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Phil\IdentityClientBundle\Application\Refresh\RefreshOutcome;
use Phil\IdentityClientBundle\Application\Refresh\RefreshStatus;
use Phil\IdentityClientBundle\Infrastructure\Store\CacheReplayStore;
use Phil\IdentityClientBundle\Infrastructure\Store\EncryptedRefreshOutcomes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(CacheReplayStore::class)]
#[CoversClass(EncryptedRefreshOutcomes::class)]
#[CoversClass(RefreshOutcome::class)]
#[CoversClass(RefreshStatus::class)]
final class StoresTest extends TestCase
{
    public function testAnIdentifierIsClaimedOnce(): void
    {
        $store = new CacheReplayStore(new ArrayAdapter(), new LockFactory(new InMemoryStore()));

        self::assertTrue($store->claim('jti', 60));
        self::assertFalse($store->claim('jti', 60));
        self::assertTrue($store->claim('another', 60));
    }

    public function testAClaimLastsForItsLifetime(): void
    {
        $clock = new MockClock();
        $pool = new ArrayAdapter(clock: $clock);
        $store = new CacheReplayStore($pool, new LockFactory(new InMemoryStore()));

        $store->claim('jti', 60);

        $clock->sleep(59);
        self::assertFalse($store->claim('jti', 60));

        $clock->sleep(2);
        self::assertTrue($store->claim('jti', 60));
    }

    public function testAClaimUnderWayRefusesAConcurrentDelivery(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $store = new CacheReplayStore(new ArrayAdapter(), $locks);
        $lock = $locks->createLock('phil_identity_client.jti.'.hash('sha256', 'jti'));
        self::assertTrue($lock->acquire());

        self::assertFalse($store->claim('jti', 60));
        $lock->release();
        self::assertTrue($store->claim('jti', 60));
    }

    public function testTheLockIsReleasedAfterAClaim(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        new CacheReplayStore(new ArrayAdapter(), $locks)->claim('jti', 60);

        self::assertTrue($locks->createLock('phil_identity_client.jti.'.hash('sha256', 'jti'))->acquire(false));
    }

    public function testTheLockIsReleasedWhenThePoolFails(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $pool = self::createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willThrowException(new RuntimeException('The pool is down.'));

        try {
            new CacheReplayStore($pool, $locks)->claim('jti', 60);
            self::fail('The pool fails.');
        } catch (RuntimeException) {
            self::assertTrue($locks->createLock('phil_identity_client.jti.'.hash('sha256', 'jti'))->acquire(false));
        }
    }

    public function testAnIdentifierIsNeverStoredInClear(): void
    {
        $pool = new ArrayAdapter();
        new CacheReplayStore($pool, new LockFactory(new InMemoryStore()))->claim('the-jti', 60);

        self::assertStringNotContainsString('the-jti', serialize($pool->getValues()));
        self::assertStringNotContainsString('the-jti', implode(',', array_keys($pool->getValues())));
    }

    public function testAnOutcomeIsReadBackWithTheSameRefreshToken(): void
    {
        $outcomes = new EncryptedRefreshOutcomes(new ArrayAdapter(), 60);
        $outcomes->publish('rt1', new RefreshOutcome(RefreshStatus::Refreshed, ['oauth2_access_token' => 'at2', 'expires' => 5]));

        $found = $outcomes->find('rt1');

        self::assertNotNull($found);
        self::assertSame(RefreshStatus::Refreshed, $found->status);
        self::assertSame(['oauth2_access_token' => 'at2', 'expires' => 5], $found->attributes);
        self::assertNull($outcomes->find('rt2'));
    }

    public function testNothingOfTheTokensIsReadableInThePool(): void
    {
        $pool = new ArrayAdapter();
        new EncryptedRefreshOutcomes($pool, 60)->publish('the-refresh-token', new RefreshOutcome(RefreshStatus::Refreshed, ['oauth2_access_token' => 'the-access-token']));

        $stored = serialize($pool->getValues()).implode(',', array_keys($pool->getValues()));
        self::assertStringNotContainsString('the-access-token', $stored);
        self::assertStringNotContainsString('the-refresh-token', $stored);
    }

    public function testTheCacheKeyIsDerivedFromTheRefreshToken(): void
    {
        $pool = new ArrayAdapter();
        new EncryptedRefreshOutcomes($pool, 60)->publish('rt', new RefreshOutcome(RefreshStatus::Revoked));

        $expected = 'phil_identity_client.refresh.'.sodium_bin2hex(substr(hash_hkdf('sha256', 'rt', 64, 'phil_identity_client.refresh_outcome'), 0, 32));
        self::assertSame([$expected], array_keys($pool->getValues()));
    }

    public function testTwoPublicationsDoNotShareANonce(): void
    {
        $pool = new ArrayAdapter();
        $outcomes = new EncryptedRefreshOutcomes($pool, 60);
        $outcome = new RefreshOutcome(RefreshStatus::Revoked);

        $outcomes->publish('rt', $outcome);
        $first = $pool->getValues();
        $outcomes->publish('rt', $outcome);

        self::assertNotSame($first, $pool->getValues());
    }

    public function testAnOutcomeExpires(): void
    {
        $clock = new MockClock();
        $outcomes = new EncryptedRefreshOutcomes(new ArrayAdapter(clock: $clock), 60);
        $outcomes->publish('rt', new RefreshOutcome(RefreshStatus::Revoked));

        $clock->sleep(59);
        self::assertNotNull($outcomes->find('rt'));

        $clock->sleep(2);
        self::assertNull($outcomes->find('rt'));
    }

    public function testAnUnavailableProviderIsRememberedBriefly(): void
    {
        $clock = new MockClock();
        $outcomes = new EncryptedRefreshOutcomes(new ArrayAdapter(clock: $clock), 60);
        $outcomes->publish('rt', new RefreshOutcome(RefreshStatus::Unavailable));

        $clock->sleep(4);
        self::assertNotNull($outcomes->find('rt'));

        $clock->sleep(2);
        self::assertNull($outcomes->find('rt'));
    }

    public function testTheBriefMemoryNeverExceedsTheConfiguredOne(): void
    {
        $clock = new MockClock();
        $outcomes = new EncryptedRefreshOutcomes(new ArrayAdapter(clock: $clock), 2);
        $outcomes->publish('rt', new RefreshOutcome(RefreshStatus::Unavailable));

        $clock->sleep(3);
        self::assertNull($outcomes->find('rt'));
    }

    public function testATamperedOrForeignEntryReadsAsNothing(): void
    {
        $pool = new ArrayAdapter();
        $outcomes = new EncryptedRefreshOutcomes($pool, 60);
        $outcomes->publish('rt', new RefreshOutcome(RefreshStatus::Revoked));

        $key = (string) array_key_first($pool->getValues());

        foreach ([str_repeat('x', 80), str_repeat('x', \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), 5] as $value) {
            $pool->save($pool->getItem($key)->set($value));
            self::assertNull($outcomes->find('rt'));
        }
    }
}
