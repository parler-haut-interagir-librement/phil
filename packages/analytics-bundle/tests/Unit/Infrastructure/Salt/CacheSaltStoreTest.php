<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Infrastructure\Salt;

use Override;
use Phil\AnalyticsBundle\Infrastructure\Salt\CacheSaltStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\BlockingStoreInterface;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(CacheSaltStore::class)]
final class CacheSaltStoreTest extends TestCase
{
    public function testTheFirstClaimKeepsItsCandidateAndTheNextOnesReadIt(): void
    {
        $store = new CacheSaltStore(new ArrayAdapter(), new LockFactory(new InMemoryStore()));

        self::assertSame('first', $store->claim('86400-20403', 'first', 3600));
        self::assertSame('first', $store->claim('86400-20403', 'second', 3600));
        self::assertSame('other', $store->claim('86400-20404', 'other', 3600));
    }

    public function testTheSaltIsForgottenWhenItsTimeIsUp(): void
    {
        $clock = new MockClock('2026-11-11 23:00:00 UTC');
        $pool = new ArrayAdapter(clock: $clock);
        $store = new CacheSaltStore($pool);

        $store->claim('86400-20403', 'first', 60);

        $clock->sleep(61);

        self::assertSame('second', $store->claim('86400-20403', 'second', 60));
    }

    public function testTwoStoresOnTheSamePoolShareTheSalt(): void
    {
        $pool = new ArrayAdapter();

        new CacheSaltStore($pool)->claim('86400-20403', 'first', 3600);

        self::assertSame('first', new CacheSaltStore($pool)->claim('86400-20403', 'second', 3600));
    }

    public function testAnEmptyOrForeignEntryIsReplaced(): void
    {
        $pool = new ArrayAdapter();
        $store = new CacheSaltStore($pool);
        $store->claim('86400-1', 'x', 3600);

        foreach ($pool->getValues() as $key => $value) {
            $pool->save($pool->getItem((string) $key)->set(''));
        }

        self::assertSame('fresh', $store->claim('86400-1', 'fresh', 3600));

        foreach (array_keys($pool->getValues()) as $key) {
            $pool->save($pool->getItem((string) $key)->set(42));
        }

        self::assertSame('again', $store->claim('86400-1', 'again', 3600));
    }

    public function testTheLockIsReleased(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        new CacheSaltStore(new ArrayAdapter(), $locks)->claim('86400-1', 'x', 3600);

        $lock = $locks->createLock('phil_analytics.salt.'.hash('xxh128', '86400-1'));

        self::assertTrue($lock->acquire());
    }

    public function testTheEntryIsKeyedByTheHashedIdentifierUnderTheBundlesPrefix(): void
    {
        $pool = new ArrayAdapter();

        new CacheSaltStore($pool)->claim('86400-1', 'x', 3600);

        self::assertSame(['phil_analytics.salt.'.hash('xxh128', '86400-1')], array_keys($pool->getValues()));
    }

    public function testTheClaimWaitsForTheLockOfItsKey(): void
    {
        $store = new class implements BlockingStoreInterface, PersistingStoreInterface {
            /**
             * @var list<string> each call, with the key it was made for
             */
            public array $calls = [];

            private bool $held = false;

            #[Override]
            public function waitAndSave(Key $key): void
            {
                $this->calls[] = 'waitAndSave '.$key;
                $this->held = true;
            }

            #[Override]
            public function save(Key $key): void
            {
                $this->calls[] = 'save '.$key;
                $this->held = true;
            }

            #[Override]
            public function delete(Key $key): void
            {
                $this->calls[] = 'delete '.$key;
                $this->held = false;
            }

            #[Override]
            public function exists(Key $key): bool
            {
                return $this->held;
            }

            #[Override]
            public function putOffExpiration(Key $key, float $ttl): void
            {
            }
        };

        new CacheSaltStore(new ArrayAdapter(), new LockFactory($store))->claim('86400-1', 'x', 3600);

        $key = 'phil_analytics.salt.'.hash('xxh128', '86400-1');

        self::assertSame(['waitAndSave '.$key, 'delete '.$key], $store->calls);
    }
}
